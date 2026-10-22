#!/usr/bin/env python3
"""Signed local delivery receipts; Python stdlib plus the system OpenSSL CLI."""
import argparse
import hashlib
import json
from pathlib import Path, PurePosixPath
import re
import subprocess
import sys
import tarfile


class DeliveryError(ValueError):
    pass


def run(*args):
    result = subprocess.run(args, capture_output=True)
    if result.returncode:
        raise DeliveryError(result.stderr.decode(errors="replace").strip() or "OpenSSL verification failed")
    return result.stdout


def trusted_key(root):
    root = Path(root)
    return root / ("resources/delivery/trusted-key.pub" if (root / "cms-project.json").exists()
                   else "resources/updates/trusted-keys/releases-2026.pub")


def digest(path):
    with path.open("rb") as stream:
        return hashlib.file_digest(stream, "sha256").hexdigest()


def artifact_name(value):
    if not isinstance(value, str) or not re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9._-]{0,159}", value):
        raise DeliveryError("Unsafe artifact name")
    if value in {"delivery.json", "delivery.sig"}:
        raise DeliveryError("Receipt cannot list itself")
    return value


def identity(sha, kind, version, lane):
    if not isinstance(sha, str) or not re.fullmatch(r"[a-f0-9]{40}", sha) or kind not in {"release", "deploy"}:
        raise DeliveryError("Invalid source SHA or delivery kind")
    if not isinstance(version, str) or not re.fullmatch(r"\d+\.\d+\.\d+", version):
        raise DeliveryError("Invalid release version")
    if lane != "release":
        raise DeliveryError("Insufficient quality lane")


def reject_duplicates(pairs):
    result = {}
    for key, value in pairs:
        if key in result:
            raise DeliveryError("Duplicate JSON field")
        result[key] = value
    return result


def read_receipt(directory, public_key):
    directory = Path(directory)
    for name in ("delivery.json", "delivery.sig"):
        path = directory / name
        if path.is_symlink() or not path.is_file():
            raise DeliveryError("Missing receipt or signature")
    if (directory / "delivery.json").stat().st_size > 1024 * 1024:
        raise DeliveryError("Oversized receipt")
    run("openssl", "dgst", "-sha256", "-verify", str(public_key), "-signature",
        str(directory / "delivery.sig"), str(directory / "delivery.json"))
    try:
        receipt = json.loads((directory / "delivery.json").read_text(), object_pairs_hook=reject_duplicates)
    except (json.JSONDecodeError, UnicodeError) as error:
        raise DeliveryError("Invalid receipt JSON") from error
    if not isinstance(receipt, dict) or type(receipt.get("schema")) is not int or receipt.get("schema") != 1:
        raise DeliveryError("Unsupported delivery schema")
    quality = receipt.get("quality")
    if not isinstance(quality, dict) or quality.get("status") != "passed":
        raise DeliveryError("Quality evidence did not pass")
    identity(receipt.get("source_sha", ""), receipt.get("kind"), receipt.get("version", ""), quality.get("lane"))
    artifacts = receipt.get("artifacts")
    if not isinstance(artifacts, list) or not artifacts or len(artifacts) > 32:
        raise DeliveryError("Missing or excessive artifacts")
    names = set()
    for item in artifacts:
        if not isinstance(item, dict):
            raise DeliveryError("Invalid artifact")
        name = artifact_name(item.get("name"))
        if name in names:
            raise DeliveryError("Duplicate artifact")
        names.add(name)
        path = directory / name
        if path.is_symlink() or not path.is_file():
            raise DeliveryError("Missing or unsafe artifact: " + name)
        if type(item.get("size")) is not int or item["size"] <= 0 or path.stat().st_size != item["size"]:
            raise DeliveryError("Artifact size mismatch: " + name)
        if not isinstance(item.get("sha256"), str) or not re.fullmatch(r"[a-f0-9]{64}", item["sha256"]):
            raise DeliveryError("Invalid artifact digest")
        if digest(path) != item["sha256"]:
            raise DeliveryError("Artifact digest mismatch: " + name)
    expected = names | {"delivery.json", "delivery.sig"}
    if {path.name for path in directory.iterdir()} != expected:
        raise DeliveryError("Unexpected files in delivery")
    required = {"runtime.tar.gz"}
    if receipt["kind"] == "release":
        version = receipt["version"]
        required |= {f"dbcms-installer-v{version}.zip", f"dbcms-v{version}.tar.gz", "checksums.txt",
                     "manifest.json", "manifest.sig", f"dbcms-update-source-v{version}.zip",
                     f"dbcms-update-runtime-v{version}.zip"}
    if not required <= names:
        raise DeliveryError("Required artifacts missing")
    return receipt


def verify(directory, sha, kind, version, public_key):
    receipt = read_receipt(directory, public_key)
    if (receipt["source_sha"], receipt["kind"], receipt["version"]) != (sha, kind, version):
        raise DeliveryError("Delivery identity does not match requested commit/kind/version")
    return receipt


def write_receipt(directory, sha, kind, version, lane, private_key, public_key):
    """Internal primitive. Only prepare-delivery supplies successful quality evidence."""
    identity(sha, kind, version, lane)
    directory = Path(directory)
    actual_key = run("openssl", "pkey", "-in", str(private_key), "-pubout", "-outform", "DER")
    expected_key = run("openssl", "pkey", "-pubin", "-in", str(public_key), "-outform", "DER")
    if actual_key != expected_key:
        raise DeliveryError("Signing key does not match pinned public key")
    artifacts = []
    for path in sorted(directory.iterdir()):
        artifact_name(path.name)
        if path.is_symlink() or not path.is_file():
            raise DeliveryError("Unsafe artifact")
        artifacts.append({"name": path.name, "size": path.stat().st_size, "sha256": digest(path)})
    receipt = {"schema": 1, "kind": kind, "source_sha": sha, "version": version,
               "quality": {"lane": lane, "status": "passed"}, "artifacts": artifacts}
    (directory / "delivery.json").write_text(json.dumps(receipt, indent=2, sort_keys=True) + "\n")
    run("openssl", "dgst", "-sha256", "-sign", str(private_key), "-out",
        str(directory / "delivery.sig"), str(directory / "delivery.json"))
    return verify(directory, sha, kind, version, public_key)


def extract_runtime(directory, output):
    output = Path(output)
    if output.is_symlink() or (output.exists() and any(output.iterdir())):
        raise DeliveryError("Runtime output must be empty")
    output.mkdir(parents=True, exist_ok=True)
    with tarfile.open(Path(directory) / "runtime.tar.gz", "r:gz") as archive:
        members = archive.getmembers()
        paths = set()
        total = 0
        for member in members:
            path = PurePosixPath(member.name)
            if member.name == "." and member.isdir():
                continue
            if path.is_absolute() or ".." in path.parts or "\\" in member.name or ":" in member.name:
                raise DeliveryError("Unsafe runtime archive path")
            normalized = str(path)
            if normalized in paths or not (member.isfile() or member.isdir()) or member.mode & 0o6000:
                raise DeliveryError("Unsafe runtime archive member")
            paths.add(normalized)
            parts = path.parts
            if not parts or any(part in {".git", ".env", "node_modules"} for part in parts):
                raise DeliveryError("Private runtime state")
            if normalized in {"storage/installed", "storage/oauth-private.key", "storage/oauth-public.key"}:
                raise DeliveryError("Installed runtime state")
            if normalized.startswith(".env.") and normalized not in {".env.example", ".env.installation.example", ".env.production.example"}:
                raise DeliveryError("Private runtime environment")
            total += member.size
            if total > 3 * 1024 ** 3 or len(paths) > 150000:
                raise DeliveryError("Oversized runtime archive")
        file_paths = {str(PurePosixPath(member.name)) for member in members if member.isfile()}
        for name in paths:
            if any(str(parent) in file_paths for parent in PurePosixPath(name).parents):
                raise DeliveryError("Runtime file shadows a directory")
        required = {"VERSION", "artisan", "vendor/autoload.php", "public/index.php", "public/build/manifest.json"}
        if not required <= file_paths:
            raise DeliveryError("Incomplete production runtime")
        version_member = next(member for member in members if str(PurePosixPath(member.name)) == "VERSION")
        if version_member.size > 64:
            raise DeliveryError("Invalid runtime version")
        version = archive.extractfile(version_member).read().decode("ascii").strip()
        identity("0" * 40, "deploy", version, "release")
        receipt_path = Path(directory) / "delivery.json"
        if receipt_path.is_file() and json.loads(receipt_path.read_text()).get("version") != version:
            raise DeliveryError("Runtime version differs from the signed receipt")
        # Links and special files have been rejected above; all parents are inside output.
        for member in members:
            destination = output.joinpath(*PurePosixPath(member.name).parts)
            if member.isdir():
                destination.mkdir(parents=True, exist_ok=True)
            else:
                destination.parent.mkdir(parents=True, exist_ok=True)
                with archive.extractfile(member) as source, destination.open("xb") as target:
                    while block := source.read(1024 * 1024):
                        target.write(block)
                destination.chmod(member.mode & 0o777)


def quick_check(root):
    root = Path(root)
    version = (root / "VERSION").read_text().strip()
    identity("0" * 40, "release", version, "release")
    package = json.loads((root / "package.json").read_text())
    lock = json.loads((root / "package-lock.json").read_text())
    if package.get("version") != version or lock.get("version") != version or lock.get("packages", {}).get("", {}).get("version") != version:
        raise DeliveryError("Node product version differs from VERSION")
    if not (root / "cms-project.json").exists():
        core = json.loads((root / "resources/updates/core-version.json").read_text())
        policy = json.loads((root / "resources/updates/release-policy.json").read_text())
        if core.get("version") != version or policy.get("version") != version:
            raise DeliveryError("Update metadata differs from VERSION")
        if policy.get("supported_from") != [version] or policy.get("rollback_compatible") is not False:
            raise DeliveryError("Release policy must identify the fresh-installation baseline")
    return version


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    commands = parser.add_subparsers(dest="command", required=True)
    check = commands.add_parser("quick-check")
    check.add_argument("--root", default=".")
    check = commands.add_parser("verify")
    for option in ("directory", "sha", "kind", "version"):
        check.add_argument("--" + option, required=True)
    check.add_argument("--public-key", default=str(trusted_key(Path(__file__).resolve().parent.parent)))
    extract = commands.add_parser("extract-runtime")
    extract.add_argument("--directory", required=True)
    extract.add_argument("--output", required=True)
    args = parser.parse_args()
    try:
        if args.command == "quick-check":
            print("Source checks passed for " + quick_check(args.root))
        elif args.command == "verify":
            verify(args.directory, args.sha, args.kind, args.version, args.public_key)
            print("Delivery signature, identity, quality and artifacts verified")
        else:
            extract_runtime(args.directory, args.output)
            print("Production runtime extracted safely")
    except (DeliveryError, OSError, tarfile.TarError, TypeError, UnicodeError, json.JSONDecodeError) as error:
        print("Delivery rejected: " + str(error), file=sys.stderr)
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
