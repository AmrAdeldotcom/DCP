#!/bin/bash
set -euo pipefail
SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
PLATFORM_ROOT="$(cd "$SCRIPT_DIR/../../../.." && pwd)/platform"
PROJECT_ROOT="$(cd "$PLATFORM_ROOT/.." && pwd)"
SOURCE="${DCP_LLAMA_SOURCE:-$PLATFORM_ROOT/dcp-env/engines/llama.cpp/source}"
[ -f "$SOURCE/CMakeLists.txt" ] || { echo "Set DCP_LLAMA_SOURCE to a llama.cpp source checkout." >&2; exit 1; }
BUILD="$PLATFORM_ROOT/dcp-env/build/llama-macos-x64-sse42"
DEST="$PLATFORM_ROOT/dcp-env/engines/llama.cpp/macos-x64-sse42"
JOBS="${DCP_BUILD_JOBS:-4}"
OPENSSL_ROOT_DIR="${OPENSSL_ROOT_DIR:-}"
OPENSSL_LICENSE_FILE="${OPENSSL_LICENSE_FILE:-}"
[ -n "$OPENSSL_ROOT_DIR" ] || { echo "Set OPENSSL_ROOT_DIR to the pinned x86_64 static OpenSSL prefix." >&2; exit 1; }
[ -s "$OPENSSL_ROOT_DIR/lib/libssl.a" ] || { echo "Static libssl.a is missing from $OPENSSL_ROOT_DIR." >&2; exit 1; }
[ -s "$OPENSSL_ROOT_DIR/lib/libcrypto.a" ] || { echo "Static libcrypto.a is missing from $OPENSSL_ROOT_DIR." >&2; exit 1; }
[ -s "$OPENSSL_LICENSE_FILE" ] || { echo "Set OPENSSL_LICENSE_FILE to the matching OpenSSL LICENSE.txt." >&2; exit 1; }
grep -q "Apache License" "$OPENSSL_LICENSE_FILE" || { echo "OpenSSL license file does not contain the Apache License notice." >&2; exit 1; }
mkdir -p "$PLATFORM_ROOT/dcp-env/build" "$PROJECT_ROOT/DCP-UserData/macOS/storage/logs"
CONFIG_LOG="$PROJECT_ROOT/DCP-UserData/macOS/storage/logs/llama-build-configure.log"
BUILD_LOG="$PROJECT_ROOT/DCP-UserData/macOS/storage/logs/llama-build.log"
on_error() {
  code=$?
  echo "llama.cpp SSE4.2 build failed (exit $code). Recent CMake/build output:" >&2
  if [ -f "$CONFIG_LOG" ]; then tail -n 80 "$CONFIG_LOG" >&2; fi
  if [ -f "$BUILD_LOG" ]; then tail -n 100 "$BUILD_LOG" >&2; fi
  exit "$code"
}
trap on_error ERR
cmake -S "$SOURCE" -B "$BUILD" \
  -DCMAKE_BUILD_TYPE=Release \
  -DCMAKE_OSX_ARCHITECTURES=x86_64 \
  -DCMAKE_OSX_DEPLOYMENT_TARGET=12.0 \
  -DOPENSSL_ROOT_DIR="$OPENSSL_ROOT_DIR" -DOPENSSL_USE_STATIC_LIBS=ON -DLLAMA_OPENSSL=ON \
  -DGGML_NATIVE=OFF -DGGML_SSE42=ON \
  -DGGML_AVX=OFF -DGGML_AVX2=OFF -DGGML_BMI2=OFF \
  -DGGML_AVX512=OFF -DGGML_FMA=OFF -DGGML_F16C=OFF \
  -DGGML_CPU_REPACK=OFF -DGGML_METAL=OFF -DGGML_CUDA=OFF \
  -DLLAMA_BUILD_SERVER=ON -DLLAMA_BUILD_UI=OFF \
  -DLLAMA_BUILD_EXAMPLES=OFF -DLLAMA_BUILD_TESTS=OFF -DLLAMA_CURL=ON \
  > "$CONFIG_LOG" 2>&1
cmake --build "$BUILD" --target llama-server -j "$JOBS" \
  > "$BUILD_LOG" 2>&1
STAGE="$DEST.new"
PREVIOUS="$DEST.previous"
rm -rf "$STAGE" "$PREVIOUS"; mkdir -p "$STAGE"
trap 'rm -rf "$STAGE"' EXIT
cp "$BUILD/bin/llama-server" "$STAGE/llama-server"
find "$BUILD/bin" -maxdepth 1 -name '*.dylib' -exec cp -f {} "$STAGE/" \;
[ ! -f "$SOURCE/LICENSE" ] || cp "$SOURCE/LICENSE" "$STAGE/LICENSE"
cp "$OPENSSL_LICENSE_FILE" "$STAGE/OpenSSL-LICENSE.txt"
for candidate in "$STAGE/llama-server" "$STAGE"/*.dylib; do
  if otool -l "$candidate" | grep -Fq "path $BUILD/bin"; then
    install_name_tool -delete_rpath "$BUILD/bin" "$candidate"
  fi
  if ! otool -l "$candidate" | grep -Fq "path @loader_path"; then
    install_name_tool -add_rpath @loader_path "$candidate"
  fi
  if otool -L "$candidate" | grep -E "lib(ssl|crypto)(\\.[0-9]+)?\\.dylib"; then
    echo "Portable llama.cpp runtime has a dynamic OpenSSL dependency: $candidate" >&2
    exit 1
  fi
  while IFS= read -r dependency; do
    case "$dependency" in
      /usr/lib/*|/System/Library/*|@rpath/*|@loader_path/*|@executable_path/*) ;;
      *) echo "Non-portable llama.cpp dependency in $candidate: $dependency" >&2; exit 1 ;;
    esac
  done < <(otool -L "$candidate" | awk 'NR > 1 { print $1 }')
done
file "$STAGE/llama-server"
"$STAGE/llama-server" --version
if [ -d "$DEST" ]; then mv "$DEST" "$PREVIOUS"; fi
if ! mv "$STAGE" "$DEST"; then
  [ ! -d "$PREVIOUS" ] || mv "$PREVIOUS" "$DEST"
  exit 1
fi
rm -rf "$PREVIOUS"
trap - ERR
echo "Generic SSE4.2 llama.cpp installed at $DEST"
