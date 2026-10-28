"""Real signatures and hostile artifact inputs through the production verifier."""
import importlib.util
import io
import json
from pathlib import Path
import subprocess
import tarfile
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SPEC = importlib.util.spec_from_file_location("delivery", ROOT / "bin/delivery.py")
delivery = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(delivery)
SHA = "a" * 40


class DeliveryTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.keys = tempfile.TemporaryDirectory()
        cls.private = Path(cls.keys.name) / "private.pem"
        cls.public = Path(cls.keys.name) / "public.pem"
        subprocess.run(["openssl", "genpkey", "-algorithm", "RSA", "-pkeyopt", "rsa_keygen_bits:3072",
                        "-out", str(cls.private)], check=True, capture_output=True)
        subprocess.run(["openssl", "pkey", "-in", str(cls.private), "-pubout", "-out", str(cls.public)],
                       check=True, capture_output=True)

    @classmethod
    def tearDownClass(cls):
        cls.keys.cleanup()

    def setUp(self):
        self.temporary = tempfile.TemporaryDirectory()
        self.bundle = Path(self.temporary.name) / "bundle"
        self.bundle.mkdir()
        self.runtime()
        delivery.write_receipt(self.bundle, SHA, "deploy", "3.0.1", "release", self.private, self.public)

    def tearDown(self):
        self.temporary.cleanup()

    def runtime(self, extra=None):
        with tarfile.open(self.bundle / "runtime.tar.gz", "w:gz") as archive:
            for name in ["VERSION", "artisan", "vendor/autoload.php", "public/index.php", "public/build/manifest.json"]:
                info = tarfile.TarInfo("./" + name)
                data = b"3.0.1\n" if name == "VERSION" else b"test bytes"
                info.size = len(data)
                archive.addfile(info, io.BytesIO(data))
            if extra:
                archive.addfile(extra)

    def resign(self, mutate):
        path = self.bundle / "delivery.json"
        receipt = json.loads(path.read_text())
        mutate(receipt)
        path.write_text(json.dumps(receipt))
        delivery.run("openssl", "dgst", "-sha256", "-sign", str(self.private), "-out",
                     str(self.bundle / "delivery.sig"), str(path))

    def verify(self, sha=SHA, kind="deploy", version="3.0.1"):
        return delivery.verify(self.bundle, sha, kind, version, self.public)

    def test_accepts_valid_signed_bundle_and_extracts_runtime(self):
        self.assertEqual(self.verify()["quality"]["lane"], "release")
        output = Path(self.temporary.name) / "runtime"
        delivery.extract_runtime(self.bundle, output)
        self.assertEqual((output / "VERSION").read_text(), "3.0.1\n")

    def test_missing_and_invalid_signature(self):
        signature = self.bundle / "delivery.sig"
        signature.unlink()
        with self.assertRaises(delivery.DeliveryError):
            self.verify()
        signature.write_bytes(b"invalid")
        with self.assertRaises(delivery.DeliveryError):
            self.verify()

    def test_wrong_delivery_identity(self):
        for identity in [("b" * 40, "deploy", "3.0.1"), (SHA, "release", "3.0.1"), (SHA, "deploy", "3.0.2")]:
            with self.subTest(identity=identity), self.assertRaises(delivery.DeliveryError):
                self.verify(*identity)

    def test_rejects_failed_and_insufficient_quality(self):
        self.resign(lambda receipt: receipt["quality"].update(status="failed"))
        with self.assertRaises(delivery.DeliveryError):
            self.verify()
        self.resign(lambda receipt: receipt.update(kind="release", quality={"lane": "integration", "status": "passed"}))
        with self.assertRaises(delivery.DeliveryError):
            self.verify(kind="release")

    def test_rejects_changed_missing_extra_and_symlink_artifacts(self):
        runtime = self.bundle / "runtime.tar.gz"
        original = runtime.read_bytes()
        runtime.write_bytes(b"changed" + original)
        with self.assertRaises(delivery.DeliveryError):
            self.verify()
        runtime.unlink()
        with self.assertRaises(delivery.DeliveryError):
            self.verify()
        outside = Path(self.temporary.name) / "outside"
        outside.write_bytes(original)
        runtime.symlink_to(outside)
        with self.assertRaises(delivery.DeliveryError):
            self.verify()
        runtime.unlink()
        runtime.write_bytes(original)
        (self.bundle / "unlisted.txt").write_text("unlisted")
        with self.assertRaises(delivery.DeliveryError):
            self.verify()

    def test_rejects_signed_unsafe_name_duplicate_and_missing_runtime(self):
        self.resign(lambda receipt: receipt["artifacts"][0].update(name="../outside"))
        with self.assertRaises(delivery.DeliveryError):
            self.verify()
        self.resign(lambda receipt: receipt["artifacts"][0].update(name="runtime.tar.gz"))
        self.resign(lambda receipt: receipt["artifacts"].append(dict(receipt["artifacts"][0])))
        with self.assertRaises(delivery.DeliveryError):
            self.verify()
        self.resign(lambda receipt: receipt.update(artifacts=[]))
        with self.assertRaises(delivery.DeliveryError):
            self.verify()

    def test_rejects_hostile_runtime_before_writing(self):
        for name in ["../escape", "/etc/passwd", ".env", "storage/installed"]:
            with self.subTest(name=name):
                self.runtime(tarfile.TarInfo(name))
                output = Path(self.temporary.name) / "runtime"
                with self.assertRaises(delivery.DeliveryError):
                    delivery.extract_runtime(self.bundle, output)
                self.assertEqual(list(output.iterdir()), [])
        symlink = tarfile.TarInfo("public/link")
        symlink.type = tarfile.SYMTYPE
        symlink.linkname = "../../outside"
        self.runtime(symlink)
        with self.assertRaises(delivery.DeliveryError):
            delivery.extract_runtime(self.bundle, output)


    def quick_root(self, version, origins, rollback=False):
        root = Path(self.temporary.name) / "quick"
        root.mkdir(parents=True, exist_ok=True)
        (root / "VERSION").write_text(version + "\n")
        (root / "package.json").write_text(json.dumps({"version": version}))
        (root / "package-lock.json").write_text(json.dumps({"version": version, "packages": {"": {"version": version}}}))
        (root / "resources").mkdir(exist_ok=True)
        (root / "resources/updates").mkdir(exist_ok=True)
        (root / "resources/updates/core-version.json").write_text(json.dumps({"version": version, "repository": "dbravoan/dbcms"}))
        (root / "resources/updates/release-policy.json").write_text(json.dumps({
            "version": version, "supported_from": origins, "rollback_compatible": rollback,
        }))
        return root

    def test_quick_check_accepts_a_published_previous_origin(self):
        self.assertEqual(delivery.quick_check(self.quick_root("3.0.1", ["3.0.1"])), "3.0.1")
        self.assertEqual(delivery.quick_check(self.quick_root("3.1.0", ["3.0.1"])), "3.1.0")

    def test_quick_check_rejects_ambiguous_or_future_origins(self):
        for origins, rollback in [([], False), (["3.0.1", "3.0.1"], False), (["3.2.0"], False), (["3.0.1"], True)]:
            with self.subTest(origins=origins, rollback=rollback), self.assertRaises(delivery.DeliveryError):
                delivery.quick_check(self.quick_root("3.1.0", origins, rollback))

    def test_signing_key_must_match_pin(self):
        for name in ("delivery.json", "delivery.sig"):
            (self.bundle / name).unlink()
        with self.assertRaises(delivery.DeliveryError):
            delivery.write_receipt(self.bundle, SHA, "deploy", "3.0.1", "release", self.private, ROOT / "resources/updates/trusted-keys/releases-2026.pub")


if __name__ == "__main__":
    unittest.main()
