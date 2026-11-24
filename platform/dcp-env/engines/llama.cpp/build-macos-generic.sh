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
file "$STAGE/llama-server"
if [ "$(uname -m)" = "x86_64" ]; then DYLD_LIBRARY_PATH="$STAGE" "$STAGE/llama-server" --version; fi
if [ -d "$DEST" ]; then mv "$DEST" "$PREVIOUS"; fi
if ! mv "$STAGE" "$DEST"; then
  [ ! -d "$PREVIOUS" ] || mv "$PREVIOUS" "$DEST"
  exit 1
fi
rm -rf "$PREVIOUS"
trap - ERR
echo "Generic SSE4.2 llama.cpp installed at $DEST"
