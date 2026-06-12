# Aggregate run-http.sh CSV into p50/p95/max per endpoint+variant.
# Usage: awk -F, -f scripts/load-test/collect.awk results.csv
BEGIN { FS = "," }
NR > 1 && $4 != "" {
    key = $1 "|" $2
    n[key]++
    vals[key, n[key]] = $5 + 0
    if ($4 != 200) errors[key]++
}
END {
    printf "%-38s %-8s %6s %8s %8s %8s %7s\n", "endpoint", "variant", "n", "p50", "p95", "max", "errors"
    for (key in n) {
        cnt = n[key]
        # insertion sort (small n)
        for (i = 1; i <= cnt; i++) a[i] = vals[key, i]
        for (i = 2; i <= cnt; i++) { v = a[i]; j = i - 1; while (j > 0 && a[j] > v) { a[j+1] = a[j]; j-- } a[j+1] = v }
        split(key, parts, "|")
        printf "%-38s %-8s %6d %7.2fs %7.2fs %7.2fs %7d\n", parts[1], parts[2], cnt, \
            a[int(cnt * 0.5) + (cnt % 2)], a[int(cnt * 0.95) < 1 ? 1 : int(cnt * 0.95)], a[cnt], errors[key] + 0
    }
}
