#!/usr/bin/env python3
"""本機商品圖庫。掃描「分類/商品」兩層文件夾，讀取 index.txt，用瀏覽器查看。"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import os
import shutil
import subprocess
import sys
import tempfile
import threading
import urllib.parse
import webbrowser
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

try:
    from PIL import Image, ImageOps, UnidentifiedImageError
except ImportError:
    print("請先在這個文件夾執行這兩行:")
    print('  python3 -m pip install --user --upgrade "pip<24.1"')
    print("  python3 -m pip install --user -r requirements.txt")
    sys.exit(1)

try:
    import pillow_heif

    pillow_heif.register_heif_opener()
    HEIC_OK = True
except ImportError:
    HEIC_OK = False

HERE = Path(__file__).resolve().parent
CONFIG_PATH = HERE / "config.json"
CACHE = HERE / ".cache"
HTML_PATH = HERE / "index.html"
IMAGE_EXT = {".jpg", ".jpeg", ".png", ".webp", ".heic", ".heif"}
HEIC_EXT = {".heic", ".heif"}
INDEX_LIMIT = 64 * 1024
THUMB_LOCK = threading.Lock()

DEFAULT_ROOT = "/Volumes/Data/SisterMart.VIP/products/raw"
ROOT: Path = Path(DEFAULT_ROOT)
PORT = 8765


def load_config() -> None:
    global ROOT, PORT
    data: dict = {}
    if CONFIG_PATH.is_file():
        try:
            data = json.loads(CONFIG_PATH.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            data = {}
    root = os.environ.get("PM_ROOT") or data.get("root") or DEFAULT_ROOT
    port = int(os.environ.get("PM_PORT") or data.get("port") or 8765)
    ROOT = Path(str(root))
    PORT = port


def save_root(root: str) -> None:
    global ROOT
    path = Path(root.strip())
    if not path.is_dir():
        raise ValueError("目錄不存在")
    ROOT = path
    stored = str(path.resolve()).replace("\\", "/")
    data = {"root": stored, "port": PORT}
    CONFIG_PATH.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def safe_path(rel: str) -> Path:
    rel = urllib.parse.unquote(rel or "").replace("\\", "/").replace("\x00", "").strip()
    if rel.startswith("/") or (len(rel) >= 2 and rel[1] == ":"):
        raise PermissionError("路徑不允許")
    parts = [p for p in rel.split("/") if p not in {"", "."}]
    if any(p == ".." or ":" in p or Path(p).is_absolute() for p in parts):
        raise PermissionError("路徑不允許")
    root = ROOT.resolve()
    try:
        full = (root.joinpath(*parts)).resolve() if parts else root
    except OSError as exc:
        raise PermissionError("路徑不允許") from exc
    if full != root and root not in full.parents:
        raise PermissionError("路徑不允許")
    return full


def parse_index(path: Path) -> list[dict[str, str]]:
    if not path.is_file():
        return []
    raw = path.read_bytes()[:INDEX_LIMIT]
    if raw.startswith(b"\xef\xbb\xbf"):
        raw = raw[3:]
    text = raw.decode("utf-8", errors="replace")
    fields: list[dict[str, str]] = []
    for line in text.splitlines():
        line = line.strip()
        if not line or line.startswith("#") or set(line) <= {"-"}:
            continue
        if "：" in line:
            key, value = line.split("：", 1)
        elif ":" in line:
            key, value = line.split(":", 1)
        else:
            key, value = "備註", line
        fields.append({"key": key.strip(), "value": value.strip()})
    return fields


def field_value(fields: list[dict[str, str]], key: str) -> str:
    for item in fields:
        if item["key"] == key and item["value"]:
            return item["value"]
    return ""


def image_record(image: Path, root: Path) -> dict | None:
    try:
        image_rel = image.resolve().relative_to(root.resolve()).as_posix()
        stat = image.stat()
    except (OSError, ValueError):
        return None
    return {
        "name": image.name,
        "path": image_rel,
        "mtime": stat.st_mtime_ns,
        "heic": image.suffix.lower() in HEIC_EXT and not can_preview_heic(),
    }


def is_appledouble(name: str) -> bool:
    return name.startswith("._")


def is_image_file(path: Path) -> bool:
    return (
        path.is_file()
        and not path.is_symlink()
        and not is_appledouble(path.name)
        and path.suffix.lower() in IMAGE_EXT
    )


def list_images(folder: Path) -> list[Path]:
    files = [path for path in folder.iterdir() if is_image_file(path)]
    return sorted(files, key=lambda p: p.name.casefold())


def read_index_text(path: Path) -> str:
    if not path.is_file() or path.is_symlink():
        return ""
    raw = path.read_bytes()[:INDEX_LIMIT]
    if raw.startswith(b"\xef\xbb\xbf"):
        raw = raw[3:]
    return raw.decode("utf-8", errors="replace")


def product_dir(rel: str) -> Path:
    rel = urllib.parse.unquote(rel or "").replace("\\", "/").replace("\x00", "").strip().strip("/")
    parts = [part for part in rel.split("/") if part not in {"", "."}]
    if len(parts) != 2 or any(part == ".." or part.startswith("._") or ":" in part for part in parts):
        raise PermissionError("路徑不允許")
    root = ROOT.resolve()
    category = root / parts[0]
    folder = category / parts[1]
    if category.is_symlink() or folder.is_symlink():
        raise PermissionError("路徑不允許")
    if not category.is_dir() or not folder.is_dir():
        raise FileNotFoundError("檔案不存在")
    resolved = folder.resolve()
    if resolved.parent != category.resolve() or category.resolve().parent != root:
        raise PermissionError("路徑不允許")
    return resolved


def write_index(rel: str, text: str) -> None:
    if not isinstance(text, str) or "\x00" in text:
        raise ValueError("內容不正確")
    folder = product_dir(rel)
    target = folder / "index.txt"
    if target.is_symlink():
        raise PermissionError("路徑不允許")
    data = text.replace("\r\n", "\n").replace("\r", "\n")
    raw = data.encode("utf-8")
    if len(raw) > INDEX_LIMIT:
        raise ValueError("內容超過 64KB")
    if data.strip() == "":
        if target.is_file():
            target.unlink()
        return
    tmp = folder / ".index.txt.tmp"
    try:
        tmp.write_bytes(raw)
        tmp.replace(target)
    finally:
        if tmp.exists():
            tmp.unlink()


INBOX_NAME = "_待整理_"
INBOX_ALIASES = {INBOX_NAME, "待整理", "inbox", "_inbox"}


def ensure_product_dir(rel: str) -> Path:
    rel = urllib.parse.unquote(rel or "").replace("\\", "/").replace("\x00", "").strip().strip("/")
    parts = [part for part in rel.split("/") if part not in {"", "."}]
    if len(parts) != 2 or any(part == ".." or part.startswith("._") or ":" in part for part in parts):
        raise PermissionError("路徑不允許")
    root = ROOT.resolve()
    if not root.is_dir():
        raise ValueError("目錄不存在")
    category = root / parts[0]
    folder = category / parts[1]
    if category.exists() and (category.is_symlink() or not category.is_dir()):
        raise PermissionError("路徑不允許")
    if folder.exists() and (folder.is_symlink() or not folder.is_dir()):
        raise PermissionError("路徑不允許")
    category.mkdir(parents=False, exist_ok=True)
    folder.mkdir(parents=False, exist_ok=True)
    resolved = folder.resolve()
    if resolved.parent != category.resolve() or category.resolve().parent != root:
        raise PermissionError("路徑不允許")
    return resolved


def unique_image_dest(folder: Path, name: str) -> Path:
    dest = folder / name
    if not dest.exists():
        return dest
    stem = Path(name).stem
    suffix = Path(name).suffix
    index = 1
    while True:
        candidate = folder / f"{stem}_{index}{suffix}"
        if not candidate.exists():
            return candidate
        index += 1


def clear_cover_reference(product_rel: str, filename: str) -> None:
    folder = product_dir(product_rel)
    index_path = folder / "index.txt"
    fields = parse_index(index_path)
    if not fields:
        return
    changed = False
    rows: list[dict[str, str]] = []
    for field in fields:
        if field["key"] == "主圖" and field["value"].casefold() == filename.casefold():
            rows.append({"key": "主圖", "value": ""})
            changed = True
        else:
            rows.append(field)
    if not changed:
        return
    text = "".join(f'{row["key"]}：{row["value"]}\n' for row in rows)
    write_index(product_rel, text)


def category_dir(name: str, create: bool = False) -> Path:
    name = urllib.parse.unquote(name or "").replace("\x00", "").strip()
    if (
        not name
        or name in {".", ".."}
        or "/" in name
        or "\\" in name
        or ":" in name
        or name.startswith(".")
    ):
        raise PermissionError("路徑不允許")
    root = ROOT.resolve()
    if not root.is_dir():
        raise ValueError("目錄不存在")
    category = root / name
    if category.is_symlink():
        raise PermissionError("路徑不允許")
    if category.exists() and not category.is_dir():
        raise PermissionError("路徑不允許")
    if not category.exists():
        if not create:
            raise FileNotFoundError("檔案不存在")
        category.mkdir(parents=False, exist_ok=False)
    resolved = category.resolve()
    if resolved.parent != root:
        raise PermissionError("路徑不允許")
    return resolved


def unique_folder_dest(category: Path, name: str) -> Path:
    dest = category / name
    if not dest.exists():
        return dest
    index = 1
    while index < 1000:
        candidate = category / f"{name}_{index}"
        if not candidate.exists():
            return candidate
        index += 1
    raise OSError("目標分類裡已有太多同名文件夾")


def move_products(paths: list, to: str) -> dict:
    if not isinstance(paths, list) or not paths:
        raise ValueError("沒有選擇商品")
    if len(paths) > 200:
        raise ValueError("一次最多移動 200 個商品")
    to = urllib.parse.unquote(str(to or "")).replace("\\", "/").strip().strip("/")
    if to in INBOX_ALIASES:
        dest_cat = category_dir(INBOX_NAME, create=True)
    else:
        dest_cat = category_dir(to, create=False)
    moved = []
    skipped = []
    failed = []
    seen: set[str] = set()
    for raw in paths:
        if not isinstance(raw, str):
            failed.append({"path": "", "error": "路徑不允許"})
            continue
        rel = urllib.parse.unquote(raw).replace("\\", "/").replace("\x00", "").strip().strip("/")
        if rel in seen:
            continue
        seen.add(rel)
        try:
            src = product_dir(rel)
            if src.parent.resolve() == dest_cat.resolve():
                skipped.append(rel)
                continue
            dest = unique_folder_dest(dest_cat, src.name)
            try:
                src.rename(dest)
            except OSError:
                shutil.move(str(src), str(dest))
            moved.append({"from": rel, "to": f"{dest_cat.name}/{dest.name}"})
        except (PermissionError, FileNotFoundError, ValueError, OSError) as exc:
            failed.append({"path": rel, "error": str(exc) or "無法移動"})
    return {"moved": moved, "skipped": skipped, "failed": failed, "catalog": scan()}


def clean_folder_name(name: str) -> str:
    name = str(name or "").replace("\x00", "").strip()
    if (
        not name
        or name in {".", ".."}
        or "/" in name
        or "\\" in name
        or ":" in name
        or name.startswith(".")
    ):
        raise PermissionError("路徑不允許")
    return name


def with_default_cover(text: str, cover: str) -> str:
    lines = text.replace("\r\n", "\n").replace("\r", "\n").split("\n")
    found = False
    for index, line in enumerate(lines):
        stripped = line.strip()
        if stripped.startswith("主圖：") or stripped.startswith("主圖:"):
            sep = "：" if "：" in stripped else ":"
            value = stripped.split(sep, 1)[1]
            if not value.strip():
                lines[index] = f"主圖：{cover}"
            found = True
            break
    if not found:
        lines.append(f"主圖：{cover}")
    return "\n".join(lines).strip("\n") + "\n"


def inbox_image_path(rel: str) -> Path:
    rel = urllib.parse.unquote(rel or "").replace("\\", "/").replace("\x00", "").strip().strip("/")
    parts = [part for part in rel.split("/") if part not in {"", "."}]
    if len(parts) != 2 or parts[0] != INBOX_NAME:
        raise PermissionError("只能歸檔_待整理_裡的圖片")
    path = safe_path(rel)
    root = ROOT.resolve()
    inbox = (root / INBOX_NAME).resolve()
    if path.parent.resolve() != inbox or not is_image_file(path):
        raise PermissionError("只能歸檔_待整理_裡的圖片")
    return path


def move_file_into(src: Path, folder: Path) -> Path:
    dest = unique_image_dest(folder, src.name)
    try:
        src.rename(dest)
    except OSError:
        shutil.move(str(src), str(dest))
    sidecar = src.parent / f"._{src.name}"
    if sidecar.is_file() and not sidecar.is_symlink():
        side_dest = folder / f"._{dest.name}"
        if side_dest.exists():
            side_dest = unique_image_dest(folder, side_dest.name)
        try:
            sidecar.rename(side_dest)
        except OSError:
            try:
                shutil.move(str(sidecar), str(side_dest))
            except OSError:
                pass
    return dest


def archive_inbox(paths: list, category: str, folder: str, text: str, merge: bool) -> dict:
    if not isinstance(paths, list) or not paths:
        raise ValueError("請先勾選圖片")
    if len(paths) > 200:
        raise ValueError("一次最多歸檔 200 張圖片")
    category = str(category or "").strip()
    if category == INBOX_NAME:
        raise ValueError("請選擇其他分類")
    dest_cat = category_dir(category, create=False)
    folder_name = clean_folder_name(folder)
    dest_folder = dest_cat / folder_name
    if dest_folder.is_symlink() or (dest_folder.exists() and not dest_folder.is_dir()):
        raise PermissionError("路徑不允許")
    if dest_folder.exists() and dest_folder.resolve().parent != dest_cat.resolve():
        raise PermissionError("路徑不允許")
    exists = dest_folder.is_dir()
    if exists and not merge:
        return {"exists": True, "catalog": scan()}
    if not isinstance(text, str):
        text = ""
    if not exists and len(text.encode("utf-8")) > INDEX_LIMIT:
        raise ValueError("內容超過 64KB")
    created = False
    if not exists:
        dest_folder.mkdir(parents=False, exist_ok=False)
        created = True
    moved = []
    failed = []
    seen: set[str] = set()
    for raw in paths:
        if not isinstance(raw, str):
            failed.append({"path": "", "error": "路徑不允許"})
            continue
        rel = urllib.parse.unquote(raw).replace("\\", "/").strip().strip("/")
        if rel in seen:
            continue
        seen.add(rel)
        try:
            src = inbox_image_path(rel)
            dest = move_file_into(src, dest_folder)
            moved.append({
                "from": rel,
                "to": f"{dest_cat.name}/{folder_name}/{dest.name}",
                "name": dest.name,
            })
        except (PermissionError, FileNotFoundError, ValueError, OSError) as exc:
            failed.append({"path": rel, "error": str(exc) or "無法移動"})
    if created and not moved:
        try:
            dest_folder.rmdir()
        except OSError:
            pass
        created = False
    if created and moved:
        write_index(f"{dest_cat.name}/{folder_name}", with_default_cover(text, moved[0]["name"]))
    return {
        "exists": False,
        "created": created,
        "merged": bool(exists and moved),
        "to": f"{dest_cat.name}/{folder_name}",
        "moved": moved,
        "failed": failed,
        "catalog": scan(),
    }


def move_image(src_rel: str, to_rel: str) -> dict:
    src_rel = urllib.parse.unquote(src_rel or "").replace("\\", "/").strip().strip("/")
    to_rel = urllib.parse.unquote(to_rel or "").replace("\\", "/").strip().strip("/")
    src = safe_path(src_rel)
    if not is_image_file(src):
        raise FileNotFoundError("檔案不存在")
    parts = [part for part in src_rel.split("/") if part]
    if len(parts) != 3:
        raise ValueError("只能移動商品文件夾裡的圖片")
    src_product = f"{parts[0]}/{parts[1]}"
    filename = parts[2]
    if to_rel in INBOX_ALIASES:
        dest_folder = category_dir(INBOX_NAME, create=True)
        if src.parent.resolve() == dest_folder.resolve():
            raise ValueError("已經在_待整理_")
        dest = move_file_into(src, dest_folder)
        try:
            clear_cover_reference(src_product, filename)
        except (FileNotFoundError, PermissionError, ValueError, OSError):
            pass
        return {
            "from": src_rel,
            "to": f"{INBOX_NAME}/{dest.name}",
            "catalog": scan(),
        }
    if to_rel == src_product:
        raise ValueError("已經在這個商品文件夾")
    dest_folder = product_dir(to_rel)
    dest = unique_image_dest(dest_folder, filename)
    src.rename(dest)
    sidecar = src.parent / f"._{filename}"
    if sidecar.is_file() and not sidecar.is_symlink():
        side_dest = dest_folder / f"._{dest.name}"
        if side_dest.exists():
            side_dest = unique_image_dest(dest_folder, f"._{dest.stem}{dest.suffix}")
        try:
            sidecar.rename(side_dest)
        except OSError:
            pass
    try:
        clear_cover_reference(src_product, filename)
    except (FileNotFoundError, PermissionError, ValueError, OSError):
        pass
    return {
        "from": src_rel,
        "to": f"{to_rel}/{dest.name}",
        "catalog": scan(),
    }


def read_product(category: str, folder: Path, root: Path) -> dict:
    fields = parse_index(folder / "index.txt")
    images = list_images(folder)
    cover_name = field_value(fields, "主圖")
    cover = next((p for p in images if p.name.casefold() == cover_name.casefold()), None)
    if cover is None and images:
        cover = images[0]

    def rel(path: Path) -> str:
        try:
            return path.resolve().relative_to(root.resolve()).as_posix()
        except ValueError:
            return ""

    image_rows = []
    for image in images:
        image_rel = rel(image)
        if not image_rel:
            continue
        stat = image.stat()
        image_rows.append(
            {
                "name": image.name,
                "path": image_rel,
                "mtime": stat.st_mtime_ns,
                "heic": image.suffix.lower() in HEIC_EXT and not can_preview_heic(),
            }
        )
    return {
        "folder": folder.name,
        "name": field_value(fields, "名稱") or folder.name,
        "category": category,
        "has_index": (folder / "index.txt").is_file() and not (folder / "index.txt").is_symlink(),
        "index_text": read_index_text(folder / "index.txt"),
        "fields": fields,
        "cover": rel(cover) if cover else "",
        "images": image_rows,
    }


def scan() -> dict:
    root = ROOT
    categories = []
    loose = 0
    if root.is_dir():
        try:
            root_entries = list(root.iterdir())
        except OSError:
            root_entries = []
        for extra in root_entries:
            if extra.is_symlink():
                continue
            if is_image_file(extra):
                loose += 1
        categories_on_disk = [
            p for p in root_entries if p.is_dir() and not p.is_symlink() and not p.name.startswith(".")
        ]
        inbox_row = None
        for cat in sorted(categories_on_disk, key=lambda p: p.name.casefold()):
            try:
                children = sorted(cat.iterdir(), key=lambda p: p.name.casefold())
            except OSError:
                children = []
            if cat.name == INBOX_NAME:
                images = []
                products = []
                for child in children:
                    if child.name.startswith(".") or child.is_symlink():
                        continue
                    if child.is_dir():
                        try:
                            products.append(read_product(cat.name, child, root))
                        except OSError:
                            continue
                    elif is_image_file(child):
                        record = image_record(child, root)
                        if record:
                            images.append(record)
                inbox_row = {
                    "name": INBOX_NAME,
                    "inbox": True,
                    "products": products,
                    "images": images,
                    "loose_images": 0,
                }
                continue
            products = []
            loose_in_cat = 0
            for child in children:
                if child.name.startswith(".") or child.is_symlink():
                    continue
                if child.is_dir():
                    try:
                        products.append(read_product(cat.name, child, root))
                    except OSError:
                        continue
                elif is_image_file(child):
                    loose_in_cat += 1
            categories.append(
                {
                    "name": cat.name,
                    "products": products,
                    "loose_images": loose_in_cat,
                }
            )
        if inbox_row:
            categories.insert(0, inbox_row)
    heic_files, heic_hidden = collect_heic(root) if root.is_dir() else ([], [])
    return {
        "root": str(root),
        "root_ok": root.is_dir(),
        "heic_ok": can_preview_heic(),
        "heic_convert_ok": can_convert_heic(),
        "heic_count": len(heic_files),
        "heic_hidden_count": len(heic_hidden),
        "loose_images": loose,
        "categories": categories,
    }


def can_preview_heic() -> bool:
    return HEIC_OK or shutil.which("sips") is not None


def can_convert_heic() -> bool:
    return can_preview_heic()


def under_root(path: Path, root: Path) -> bool:
    try:
        resolved = path.resolve()
        base = root.resolve()
    except OSError:
        return False
    return resolved == base or base in resolved.parents


def collect_heic(root: Path | None = None) -> tuple[list[Path], list[Path]]:
    base = root or ROOT
    real: list[Path] = []
    hidden: list[Path] = []
    if not base.is_dir():
        return real, hidden
    try:
        paths = list(base.rglob("*"))
    except OSError:
        return real, hidden
    for path in paths:
        try:
            if path.is_symlink() or not path.is_file():
                continue
            if path.suffix.lower() not in HEIC_EXT:
                continue
            if not under_root(path, base):
                continue
        except OSError:
            continue
        if is_appledouble(path.name):
            hidden.append(path)
        else:
            real.append(path)
    real.sort(key=lambda p: p.as_posix().casefold())
    hidden.sort(key=lambda p: p.as_posix().casefold())
    return real, hidden


def heic_rel(path: Path, root: Path | None = None) -> str:
    base = (root or ROOT).resolve()
    try:
        return path.resolve().relative_to(base).as_posix()
    except ValueError:
        return path.name


def convert_heic_file(src: Path, dest: Path) -> None:
    dest.parent.mkdir(parents=True, exist_ok=True)
    if shutil.which("sips"):
        result = subprocess.run(
            [
                "sips",
                "-s",
                "format",
                "jpeg",
                "-s",
                "formatOptions",
                "90",
                str(src),
                "--out",
                str(dest),
            ],
            capture_output=True,
            timeout=120,
            check=False,
        )
        if result.returncode != 0 or not dest.is_file() or dest.stat().st_size <= 0:
            detail = (result.stderr or result.stdout or b"").decode("utf-8", errors="replace").strip()
            raise OSError(detail or "sips 轉換失敗")
        return
    if not HEIC_OK:
        raise OSError("這個環境無法轉換 HEIC。請在 Mac 上使用，或安裝 pillow-heif。")
    with Image.open(src) as raw:
        raw.load()
        oriented = ImageOps.exif_transpose(raw) or raw
        if oriented.mode in {"RGBA", "LA"} or (oriented.mode == "P" and "transparency" in oriented.info):
            rgba = oriented.convert("RGBA")
            canvas = Image.new("RGB", rgba.size, (255, 255, 255))
            canvas.paste(rgba, mask=rgba.getchannel("A"))
            rgb = canvas
        else:
            rgb = oriented.convert("RGB")
        tmp = dest.with_suffix(dest.suffix + ".tmp")
        rgb.save(tmp, "JPEG", quality=90)
        if oriented is not raw:
            oriented.close()
        tmp.replace(dest)


def convert_all_heic() -> dict:
    root = ROOT
    if not root.is_dir():
        raise ValueError("目錄不存在")
    if not can_convert_heic():
        raise ValueError("這個環境無法轉換 HEIC。請在 Mac 上使用系統自帶工具，或安裝 pillow-heif。")
    real, hidden = collect_heic(root)
    logs: list[str] = []
    converted = 0
    skipped = 0
    failed = 0
    deleted = 0
    logs.append(f"開始轉換，共 {len(real)} 個 HEIC，另有 {len(hidden)} 個隱藏 HEIC。")
    for src in real:
        rel = heic_rel(src, root)
        dest = src.with_suffix(".jpg")
        try:
            if dest.exists():
                logs.append(f"已有 JPG，跳過轉換：{rel}")
                skipped += 1
            else:
                convert_heic_file(src, dest)
                logs.append(f"已轉換：{rel} → {dest.name}")
                converted += 1
            if src.exists():
                src.unlink()
                deleted += 1
                logs.append(f"已刪除：{rel}")
        except Exception as exc:  # noqa: BLE001 - collect per-file failures for the UI log
            failed += 1
            logs.append(f"失敗：{rel}（{exc}）")
    hidden_deleted = 0
    for src in hidden:
        rel = heic_rel(src, root)
        try:
            if src.exists():
                src.unlink()
                hidden_deleted += 1
                deleted += 1
                logs.append(f"已刪除隱藏檔：{rel}")
        except OSError as exc:
            failed += 1
            logs.append(f"隱藏檔刪除失敗：{rel}（{exc}）")
    logs.append(
        f"完成。轉換 {converted}，跳過 {skipped}，刪除 {deleted}，失敗 {failed}。"
    )
    return {
        "ok": failed == 0,
        "converted": converted,
        "skipped": skipped,
        "deleted": deleted,
        "hidden_deleted": hidden_deleted,
        "failed": failed,
        "logs": logs,
        "catalog": scan(),
    }


def sips_jpeg(src: Path, edge: int, quality: int) -> bytes:
    sips = shutil.which("sips")
    if not sips:
        raise UnidentifiedImageError("HEIC")
    with tempfile.TemporaryDirectory(prefix="pm-heic-") as tmp:
        dest = Path(tmp) / "preview.jpg"
        result = subprocess.run(
            [
                sips,
                "-s",
                "format",
                "jpeg",
                "-s",
                "formatOptions",
                str(int(quality)),
                "-Z",
                str(int(edge)),
                str(src),
                "--out",
                str(dest),
            ],
            capture_output=True,
            timeout=60,
            check=False,
        )
        data = dest.read_bytes() if dest.is_file() else b""
        if result.returncode != 0 or not data.startswith(b"\xff\xd8"):
            raise UnidentifiedImageError("HEIC")
        return data


def jpeg_bytes(src: Path, edge: int, quality: int) -> bytes:
    if src.suffix.lower() in HEIC_EXT and not HEIC_OK:
        return sips_jpeg(src, edge, quality)
    with Image.open(src) as raw:
        raw.load()
        oriented = ImageOps.exif_transpose(raw) or raw
        if oriented.mode in {"RGBA", "LA"} or (oriented.mode == "P" and "transparency" in oriented.info):
            rgba = oriented.convert("RGBA")
            canvas = Image.new("RGB", rgba.size, (255, 255, 255))
            canvas.paste(rgba, mask=rgba.getchannel("A"))
            rgb = canvas
        else:
            rgb = oriented.convert("RGB")
        rgb.thumbnail((edge, edge))
        buf = io.BytesIO()
        rgb.save(buf, "JPEG", quality=quality)
        if oriented is not raw:
            oriented.close()
        return buf.getvalue()


def jpeg_cache(kind: str, rel: str, src: Path, edge: int, quality: int) -> bytes:
    stat = src.stat()
    key = hashlib.sha1(f"{kind}|{rel}|{stat.st_mtime_ns}|{stat.st_size}|{edge}".encode("utf-8")).hexdigest()
    dest = CACHE / kind / f"{key}.jpg"
    if not dest.is_file():
        with THUMB_LOCK:
            if not dest.is_file():
                dest.parent.mkdir(parents=True, exist_ok=True)
                tmp = dest.with_suffix(".tmp")
                tmp.write_bytes(jpeg_bytes(src, edge, quality))
                tmp.replace(dest)
    return dest.read_bytes()


class Handler(BaseHTTPRequestHandler):
    protocol_version = "HTTP/1.1"

    def do_GET(self) -> None:
        parsed = urllib.parse.urlparse(self.path)
        query = urllib.parse.parse_qs(parsed.query)
        path = parsed.path
        try:
            if path in {"/", "/index.html"}:
                self._file(HTML_PATH, "text/html; charset=utf-8", cache=False)
            elif path == "/api/catalog":
                self._json(scan())
            elif path == "/favicon.ico":
                self.send_response(204)
                self.send_header("Content-Length", "0")
                self.end_headers()
            elif path in {"/thumb", "/image"}:
                self._picture(path, query.get("path", [""])[0])
            else:
                self._json({"error": "找不到"}, 404)
        except PermissionError as exc:
            self._json({"error": str(exc)}, 403)
        except FileNotFoundError:
            self._json({"error": "檔案不存在"}, 404)
        except (UnidentifiedImageError, Image.DecompressionBombError, OSError):
            self._json({"error": "這張圖無法預覽"}, 415)

    def do_POST(self) -> None:
        parsed = urllib.parse.urlparse(self.path)
        if parsed.path == "/api/config":
            self._post_config()
            return
        if parsed.path == "/api/index":
            self._post_index()
            return
        if parsed.path == "/api/heic/convert":
            self._post_heic_convert()
            return
        if parsed.path == "/api/image/move":
            self._post_image_move()
            return
        if parsed.path == "/api/product/move":
            self._post_product_move()
            return
        if parsed.path == "/api/inbox/archive":
            self._post_inbox_archive()
            return
        self._json({"error": "找不到"}, 404)

    def _read_json(self, limit: int) -> dict:
        length = int(self.headers.get("Content-Length") or 0)
        if length <= 0 or length > limit:
            raise ValueError("內容不正確")
        try:
            body = json.loads(self.rfile.read(length).decode("utf-8"))
        except json.JSONDecodeError as exc:
            raise ValueError("內容不正確") from exc
        if not isinstance(body, dict):
            raise ValueError("內容不正確")
        return body

    def _post_config(self) -> None:
        try:
            body = self._read_json(8192)
            root = str(body.get("root") or "").strip()
            if not root:
                raise ValueError("請填寫商品圖目錄")
            save_root(root)
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
            return
        except PermissionError as exc:
            self._json({"error": str(exc)}, 403)
            return
        self._json(scan())

    def _post_index(self) -> None:
        try:
            body = self._read_json(INDEX_LIMIT + 4096)
            if "text" not in body or not isinstance(body.get("text"), str):
                raise ValueError("請填寫 index.txt 內容")
            write_index(str(body.get("path") or ""), body["text"])
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
            return
        except PermissionError as exc:
            self._json({"error": str(exc)}, 403)
            return
        except FileNotFoundError:
            self._json({"error": "檔案不存在"}, 404)
            return
        self._json(scan())

    def _post_heic_convert(self) -> None:
        length = int(self.headers.get("Content-Length") or 0)
        if length > 0:
            self.rfile.read(length)
        try:
            result = convert_all_heic()
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
            return
        except PermissionError as exc:
            self._json({"error": str(exc)}, 403)
            return
        self._json(result)

    def _post_image_move(self) -> None:
        try:
            body = self._read_json(8192)
            src = str(body.get("path") or "").strip()
            dest = str(body.get("to") or "").strip()
            if not src or not dest:
                raise ValueError("請選擇要移動的圖片和目標商品")
            result = move_image(src, dest)
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
            return
        except PermissionError as exc:
            self._json({"error": str(exc)}, 403)
            return
        except FileNotFoundError:
            self._json({"error": "檔案不存在"}, 404)
            return
        except OSError as exc:
            self._json({"error": f"無法移動：{exc}"}, 500)
            return
        self._json(result)

    def _post_product_move(self) -> None:
        try:
            body = self._read_json(65536)
            dest = str(body.get("to") or "").strip()
            paths = body.get("paths")
            if not dest:
                raise ValueError("請選擇目標分類")
            result = move_products(paths, dest)
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
            return
        except PermissionError as exc:
            self._json({"error": str(exc)}, 403)
            return
        except FileNotFoundError:
            self._json({"error": "檔案不存在"}, 404)
            return
        except OSError as exc:
            self._json({"error": f"無法移動：{exc}"}, 500)
            return
        self._json(result)

    def _post_inbox_archive(self) -> None:
        try:
            body = self._read_json(INDEX_LIMIT + 65536)
            result = archive_inbox(
                body.get("paths"),
                str(body.get("category") or ""),
                str(body.get("folder") or ""),
                body.get("text") if isinstance(body.get("text"), str) else "",
                bool(body.get("merge")),
            )
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
            return
        except PermissionError as exc:
            self._json({"error": str(exc)}, 403)
            return
        except FileNotFoundError:
            self._json({"error": "檔案不存在"}, 404)
            return
        except OSError as exc:
            self._json({"error": f"無法歸檔：{exc}"}, 500)
            return
        self._json(result)

    def _picture(self, kind: str, rel: str) -> None:
        src = safe_path(rel)
        if not is_image_file(src):
            raise FileNotFoundError
        edge = 320 if kind == "/thumb" else 1600
        quality = 80 if kind == "/thumb" else 86
        direct = kind == "/image" and src.suffix.lower() not in HEIC_EXT
        if direct:
            with Image.open(src) as image:
                small = max(image.size) <= edge
        else:
            small = False
        if direct and small:
            mime = {
                ".jpg": "image/jpeg",
                ".jpeg": "image/jpeg",
                ".png": "image/png",
                ".webp": "image/webp",
            }.get(src.suffix.lower(), "application/octet-stream")
            self._file(src, mime, cache=True)
            return
        data = jpeg_cache("thumb" if kind == "/thumb" else "preview", rel, src, edge, quality)
        self._bytes(data, "image/jpeg", cache=True)

    def _json(self, data: dict, status: int = 200) -> None:
        body = json.dumps(data, ensure_ascii=False).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def _file(self, path: Path, content_type: str, cache: bool) -> None:
        data = path.read_bytes()
        self._bytes(data, content_type, cache)

    def _bytes(self, data: bytes, content_type: str, cache: bool) -> None:
        self.send_response(200)
        self.send_header("Content-Type", content_type)
        self.send_header("Content-Length", str(len(data)))
        self.send_header("Cache-Control", "public, max-age=86400" if cache else "no-store")
        self.end_headers()
        self.wfile.write(data)


def main() -> None:
    parser = argparse.ArgumentParser(description="SisterMart 本地商品圖庫")
    parser.add_argument("--no-browser", action="store_true")
    parser.add_argument("--root")
    parser.add_argument("--port", type=int)
    args = parser.parse_args()
    load_config()
    global ROOT, PORT
    if args.root:
        ROOT = Path(args.root)
    if args.port:
        PORT = args.port
    try:
        server = ThreadingHTTPServer(("127.0.0.1", PORT), Handler)
    except OSError as exc:
        print(f"無法在 127.0.0.1:{PORT} 啟動。這個連接埠可能已在使用。")
        print(exc)
        sys.exit(1)
    url = f"http://127.0.0.1:{PORT}/"
    print(f"商品圖庫  {url}")
    print(f"目錄      {ROOT}")
    if not can_preview_heic():
        print("HEIC 預覽未啟用。需要時執行: python3 -m pip install pillow-heif")
    if not args.no_browser:
        threading.Timer(0.4, lambda: webbrowser.open(url)).start()
    try:
        server.serve_forever()
    except KeyboardInterrupt:
        print("\n已停止")
    finally:
        server.server_close()


if __name__ == "__main__":
    main()
