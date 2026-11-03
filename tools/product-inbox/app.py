#!/usr/bin/env python3
"""本機商品圖庫。掃描「分類/商品」兩層文件夾，讀取 index.txt，用瀏覽器查看。"""

from __future__ import annotations

import argparse
import hashlib
import io
import json
import os
import sys
import threading
import urllib.parse
import webbrowser
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer
from pathlib import Path

try:
    from PIL import Image, ImageOps, UnidentifiedImageError
except ImportError:
    print("請先在這個文件夾執行: py -3 -m pip install -r requirements.txt")
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

ROOT: Path = Path("D:/SisterMart-PM")
PORT = 8765


def load_config() -> None:
    global ROOT, PORT
    data: dict = {}
    if CONFIG_PATH.is_file():
        try:
            data = json.loads(CONFIG_PATH.read_text(encoding="utf-8"))
        except json.JSONDecodeError:
            data = {}
    root = os.environ.get("PM_ROOT") or data.get("root") or "D:/SisterMart-PM"
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
        if not line or line.startswith("#"):
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


def list_images(folder: Path) -> list[Path]:
    files = []
    for path in folder.iterdir():
        if path.is_symlink() or not path.is_file():
            continue
        if path.suffix.lower() not in IMAGE_EXT:
            continue
        files.append(path)
    return sorted(files, key=lambda p: p.name.casefold())


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
                "heic": image.suffix.lower() in HEIC_EXT and not HEIC_OK,
            }
        )
    return {
        "folder": folder.name,
        "name": field_value(fields, "名稱") or folder.name,
        "category": category,
        "has_index": (folder / "index.txt").is_file(),
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
            if extra.is_file() and extra.suffix.lower() in IMAGE_EXT:
                loose += 1
        categories_on_disk = [
            p for p in root_entries if p.is_dir() and not p.is_symlink() and not p.name.startswith(".")
        ]
        for cat in sorted(categories_on_disk, key=lambda p: p.name.casefold()):
            products = []
            loose_in_cat = 0
            try:
                children = sorted(cat.iterdir(), key=lambda p: p.name.casefold())
            except OSError:
                children = []
            for child in children:
                if child.name.startswith(".") or child.is_symlink():
                    continue
                if child.is_dir():
                    try:
                        products.append(read_product(cat.name, child, root))
                    except OSError:
                        continue
                elif child.is_file() and not child.is_symlink() and child.suffix.lower() in IMAGE_EXT:
                    loose_in_cat += 1
            categories.append(
                {
                    "name": cat.name,
                    "products": products,
                    "loose_images": loose_in_cat,
                }
            )
    return {
        "root": str(root),
        "root_ok": root.is_dir(),
        "heic_ok": HEIC_OK,
        "loose_images": loose,
        "categories": categories,
    }


def jpeg_bytes(src: Path, edge: int, quality: int) -> bytes:
    if src.suffix.lower() in HEIC_EXT and not HEIC_OK:
        raise UnidentifiedImageError("HEIC")
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
        if parsed.path != "/api/config":
            self._json({"error": "找不到"}, 404)
            return
        length = int(self.headers.get("Content-Length") or 0)
        if length <= 0 or length > 8192:
            self._json({"error": "內容不正確"}, 400)
            return
        try:
            body = json.loads(self.rfile.read(length).decode("utf-8"))
            root = str(body.get("root") or "").strip()
            if not root:
                raise ValueError("請填寫商品圖目錄")
            save_root(root)
        except ValueError as exc:
            self._json({"error": str(exc)}, 400)
            return
        except json.JSONDecodeError:
            self._json({"error": "內容不正確"}, 400)
            return
        self._json(scan())

    def _picture(self, kind: str, rel: str) -> None:
        src = safe_path(rel)
        if not src.is_file() or src.suffix.lower() not in IMAGE_EXT:
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
    if not HEIC_OK:
        print("HEIC 預覽未啟用。需要時執行: py -3 -m pip install pillow-heif")
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
