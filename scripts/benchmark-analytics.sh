#!/usr/bin/env bash
set -euo pipefail

# benchmark-analytics.sh
# Runner script for lecar-bi analytics performance benchmarking and baseline evidence collection.

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
BACKEND_DIR="${ROOT_DIR}/backend"

PROFILE="${PROFILE:-large}"
WORKSPACE="${WORKSPACE:-perf-ws-1}"
RUNS="${RUNS:-30}"
WARMUP="${WARMUP:-5}"
CACHE_STATE="${CACHE_STATE:-disabled}"
FORMAT="${FORMAT:-json}"
SCENARIO="${SCENARIO:-}"
OUTPUT="${OUTPUT:-}"
EXPLAIN="${EXPLAIN:-false}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --profile=*)
      PROFILE="${1#*=}"
      shift
      ;;
    --workspace=*)
      WORKSPACE="${1#*=}"
      shift
      ;;
    --runs=*)
      RUNS="${1#*=}"
      shift
      ;;
    --warmup=*)
      WARMUP="${1#*=}"
      shift
      ;;
    --cache-state=*)
      CACHE_STATE="${1#*=}"
      shift
      ;;
    --format=*)
      FORMAT="${1#*=}"
      shift
      ;;
    --scenario=*)
      SCENARIO="${1#*=}"
      shift
      ;;
    --output=*)
      OUTPUT="${1#*=}"
      shift
      ;;
    --explain)
      EXPLAIN="true"
      shift
      ;;
    -h|--help)
      echo "Usage: $0 [options]"
      echo "Options:"
      echo "  --profile=NAME        Dataset profile (small|large, default: large)"
      echo "  --workspace=ID        Isolated workspace ID (default: perf-ws-1, must start with perf-)"
      echo "  --runs=N              Measured runs count (default: 30)"
      echo "  --warmup=N            Warmup runs count (default: 5)"
      echo "  --cache-state=STATE   Cache state: disabled|cold|warm (default: disabled)"
      echo "  --format=FORMAT       Output format: json|table (default: json)"
      echo "  --scenario=ID         Specific scenario ID (e.g. SALES-01)"
      echo "  --output=PATH         Path to write JSON output file"
      echo "  --explain             Collect EXPLAIN (ANALYZE, BUFFERS, FORMAT JSON) for queries"
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      exit 1
      ;;
  esac
done

CMD=(php artisan performance:benchmark)
CMD+=(--profile="${PROFILE}")
CMD+=(--workspace="${WORKSPACE}")
CMD+=(--runs="${RUNS}")
CMD+=(--warmup="${WARMUP}")
CMD+=(--cache-state="${CACHE_STATE}")
CMD+=(--format="${FORMAT}")

if [[ -n "${SCENARIO}" ]]; then
  CMD+=(--scenario="${SCENARIO}")
fi

if [[ -n "${OUTPUT}" ]]; then
  CMD+=(--output="${OUTPUT}")
fi

if [[ "${EXPLAIN}" == "true" ]]; then
  CMD+=(--explain)
fi

echo "Running analytics benchmark in ${BACKEND_DIR}:" >&2
echo "${CMD[*]}" >&2

cd "${BACKEND_DIR}"
exec "${CMD[@]}"
