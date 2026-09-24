#!/usr/bin/env python3

import secrets
import shutil
from pathlib import Path


ROOT = Path(__file__).resolve().parent.parent
ENV_FILE = ROOT / "infra" / ".env.dev"
ENV_EXAMPLE = ROOT / "infra" / ".env.example"
SECRET_NAMES = ("SESSION_SECRET", "SUPPORT_BFF_SHARED_SECRET")
DEV_PORTS = {
    "POSTGRES_PORT": "15432",
    "NOTIFICATION_POSTGRES_PORT": "15433",
    "REDIS_PORT": "16379",
}


def prepare_dev_env():
    if not ENV_FILE.exists():
        shutil.copyfile(ENV_EXAMPLE, ENV_FILE)
        lines = ENV_FILE.read_text().splitlines()
        for index, line in enumerate(lines):
            name = line.partition("=")[0]
            if name in DEV_PORTS: lines[index] = f"{name}={DEV_PORTS[name]}"
        ENV_FILE.write_text("\n".join(lines) + "\n")

    lines = ENV_FILE.read_text().splitlines()
    updated = False
    for secret_name in SECRET_NAMES:
        secret_lines = [line for line in lines if line.startswith(f"{secret_name}=")]
        value = secret_lines[-1].partition("=")[2].strip() if secret_lines else ""
        if value and not value.startswith("replace-with-"): continue

        lines = [line for line in lines if not line.startswith(f"{secret_name}=")]
        lines.append(f"{secret_name}={secrets.token_hex(32)}")
        updated = True

    if updated: ENV_FILE.write_text("\n".join(lines) + "\n")

    ENV_FILE.chmod(0o600)


if __name__ == "__main__":
    prepare_dev_env()
