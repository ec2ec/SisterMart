#!/bin/bash
cd "$(dirname "$0")"
export PYTHONUTF8=1
export PYTHONIOENCODING=utf-8
if [ -x .venv/bin/python3 ]; then
  PY=".venv/bin/python3"
elif command -v python3 >/dev/null 2>&1; then
  PY="python3"
else
  echo "找不到 python3。請到 https://www.python.org/downloads/macos/ 安裝 Python 3。"
  read -r -p "按 Enter 關閉"
  exit 1
fi
"$PY" app.py
status=$?
if [ "$status" -ne 0 ]; then
  read -r -p "按 Enter 關閉"
fi
exit "$status"
