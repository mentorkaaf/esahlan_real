"""Patch livekit_client null-safety bug (Dart 3.6+ strict promotion after await)."""
import sys
import os
import glob

pattern = os.path.expanduser(
    "~/.pub-cache/hosted/pub.dev/livekit_client*/lib/src/participant/local.dart"
)
matches = glob.glob(pattern)

if not matches:
    print("WARNING: livekit local.dart not found — skipping patch")
    sys.exit(0)

path = matches[0]
with open(path, "r") as f:
    content = f.read()

content = content.replace("publishOptions.videoCodec", "publishOptions!.videoCodec")
content = content.replace("publishOptions.degradationPreference", "publishOptions!.degradationPreference")
content = content.replace("publishOptions.backupVideoCodec", "publishOptions!.backupVideoCodec")

with open(path, "w") as f:
    f.write(content)

print("livekit_client patched: " + path)
