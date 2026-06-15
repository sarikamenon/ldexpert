#!/usr/bin/env python3
"""Build a rich, self-contained HTML report from PHPUnit/JUnit XML produced by Laravel Dusk.

The output is a single .html file — no external dependencies, works from file://.
"""

from __future__ import annotations

import argparse
import html
import sys
import xml.etree.ElementTree as ElementTree
from datetime import datetime, timezone
from pathlib import Path


def parse_junit(path: Path) -> tuple[list[dict[str, str]], dict[str, str]]:
    tree = ElementTree.parse(path)
    root = tree.getroot()

    cases: list[dict[str, str]] = []
    for case in root.iter("testcase"):
        failure = case.find("failure")
        error = case.find("error")
        skipped = case.find("skipped")

        if failure is not None:
            status = "failed"
            detail = failure.text or failure.attrib.get("message", "")
        elif error is not None:
            status = "error"
            detail = error.text or error.attrib.get("message", "")
        elif skipped is not None:
            status = "skipped"
            detail = skipped.attrib.get("message", "")
        else:
            status = "passed"
            detail = ""

        cases.append({
            "name": case.attrib.get("name", ""),
            "class": case.attrib.get("classname", ""),
            "time": case.attrib.get("time", "0"),
            "status": status,
            "detail": detail.strip(),
        })

    total_time = 0.0
    for case in cases:
        try:
            total_time += float(case["time"])
        except (TypeError, ValueError):
            pass

    summary = {
        "tests": str(len(cases)),
        "failures": str(sum(1 for c in cases if c["status"] == "failed")),
        "errors": str(sum(1 for c in cases if c["status"] == "error")),
        "skipped": str(sum(1 for c in cases if c["status"] == "skipped")),
        "time": f"{total_time:.2f}",
    }

    return cases, summary


def render_md(cases: list[dict[str, str]], summary: dict[str, str], junit_path: Path) -> str:
    failed = int(summary.get("failures", 0))
    errors = int(summary.get("errors", 0))
    passed = max(0, int(summary.get("tests", 0)) - failed - errors - int(summary.get("skipped", 0)))
    generated = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")

    status_icon = {
        "passed": "PASS",
        "failed": "FAIL",
        "error": "ERROR",
        "skipped": "SKIP",
    }

    lines = [
        "# BrowserQA Dusk Report",
        "",
        f"Generated {generated}",
        "",
        "## Summary",
        "",
        f"- **Total: {summary.get('tests', '0')} | "
        f"Passed: {passed} | "
        f"Failed: {summary.get('failures', '0')} | "
        f"Errors: {summary.get('errors', '0')} | "
        f"Skipped: {summary.get('skipped', '0')}**",
        f"- Duration: {summary.get('time', '0')}s",
        "",
        "## Results",
        "",
        "| Status | Test | Time (s) | Details |",
        "|--------|------|----------|---------|",
    ]

    for case in cases:
        detail = (case["detail"] or "-").replace("|", "\\|").replace("\n", " ").strip()
        if len(detail) > 300:
            detail = detail[:297] + "..."
        status = status_icon.get(case["status"], case["status"])
        lines.append(f"| {status} | {case['name']} | {case['time']} | {detail} |")

    if not cases:
        lines.append("| - | No test cases recorded. | - | - |")

    lines.append("")
    return "\n".join(lines)


def donut_svg(passed: int, failed: int, skipped: int) -> str:
    total = passed + failed + skipped
    if total == 0:
        return "<svg width='160' height='160'><circle cx='80' cy='80' r='60' fill='none' stroke='#e5e7eb' stroke-width='20'/></svg>"

    def arc(value: int, color: str, offset: float) -> str:
        pct = value / total
        circ = 2 * 3.14159 * 60
        dash = pct * circ
        gap = circ - dash
        return (
            f"<circle cx='80' cy='80' r='60' fill='none' stroke='{color}' stroke-width='20' "
            f"stroke-dasharray='{dash:.2f} {gap:.2f}' "
            f"stroke-dashoffset='{offset:.2f}' transform='rotate(-90 80 80)'/>"
        )

    circ = 2 * 3.14159 * 60
    pass_off = 0.0
    fail_off = -(passed / total) * circ
    skip_off = -((passed + failed) / total) * circ

    pass_pct = int(passed / total * 100)

    return f"""<svg width='160' height='160' viewBox='0 0 160 160'>
  {arc(passed, '#22c55e', pass_off)}
  {arc(failed, '#ef4444', fail_off)}
  {arc(skipped, '#f59e0b', skip_off)}
  <text x='80' y='76' text-anchor='middle' font-size='22' font-weight='700' fill='#111'>{pass_pct}%</text>
  <text x='80' y='96' text-anchor='middle' font-size='11' fill='#6b7280'>passed</text>
</svg>"""


def render_html(cases: list[dict[str, str]], summary: dict[str, str], junit_path: Path) -> str:
    failed_count = int(summary.get("failures", 0))
    error_count = int(summary.get("errors", 0))
    skipped_count = int(summary.get("skipped", 0))
    total_count = int(summary.get("tests", 0))
    passed_count = max(0, total_count - failed_count - error_count - skipped_count)
    generated = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M:%S UTC")
    suite_name = junit_path.stem.replace("-", " ").title()

    # Group cases by class
    groups: dict[str, list[dict[str, str]]] = {}
    for case in cases:
        cls = case["class"] or "Unknown"
        short = cls.split("\\")[-1] if "\\" in cls else cls
        groups.setdefault(short, []).append(case)

    # Build rows grouped by class
    rows_html_parts = []
    for cls_name, cls_cases in groups.items():
        cls_pass = sum(1 for c in cls_cases if c["status"] == "passed")
        cls_fail = sum(1 for c in cls_cases if c["status"] in ("failed", "error"))
        cls_skip = sum(1 for c in cls_cases if c["status"] == "skipped")
        group_status = "fail" if cls_fail > 0 else "pass"
        rows_html_parts.append(
            f"<tr class='group-header {group_status}'>"
            f"<td colspan='4'><strong>{html.escape(cls_name)}</strong>"
            f"<span class='group-counts'>{cls_pass} passed"
            + (f" · <span style='color:#ef4444'>{cls_fail} failed</span>" if cls_fail else "")
            + (f" · {cls_skip} skipped" if cls_skip else "")
            + "</span></td></tr>"
        )
        for case in cls_cases:
            status = case["status"]
            if status in ("failed", "error"):
                badge = "<span class='badge bad'>FAIL</span>"
                tr_class = "row-fail"
            elif status == "skipped":
                badge = "<span class='badge warn'>SKIP</span>"
                tr_class = "row-skip"
            else:
                badge = "<span class='badge ok'>PASS</span>"
                tr_class = "row-pass"

            detail_html = ""
            if case["detail"]:
                esc = html.escape(case["detail"])
                uid = f"d{abs(hash(case['name'] + case['class']))}"
                detail_html = (
                    f"<details id='{uid}'><summary>Show details</summary>"
                    f"<pre class='detail-pre'>{esc}</pre></details>"
                )

            rows_html_parts.append(
                f"<tr class='test-row {tr_class}' data-status='{status}'>"
                f"<td>{badge}</td>"
                f"<td class='test-name'>{html.escape(case['name'])}</td>"
                f"<td class='time-cell'>{html.escape(case['time'])}s</td>"
                f"<td>{detail_html}</td>"
                f"</tr>"
            )

    rows_html = "\n".join(rows_html_parts) if rows_html_parts else "<tr><td colspan='4'>No test cases recorded.</td></tr>"
    donut = donut_svg(passed_count, failed_count + error_count, skipped_count)
    overall_class = "overall-fail" if (failed_count + error_count) > 0 else "overall-pass"

    return f"""<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>QA Report — {html.escape(suite_name)}</title>
  <style>
    *, *::before, *::after {{ box-sizing: border-box; margin: 0; padding: 0; }}
    body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
           background: #f8fafc; color: #0f172a; min-height: 100vh; }}
    .topbar {{ background: #1e293b; color: #f8fafc; padding: 1rem 2rem;
              display: flex; align-items: center; justify-content: space-between; }}
    .topbar h1 {{ font-size: 1.1rem; font-weight: 600; letter-spacing: 0.02em; }}
    .topbar .meta {{ font-size: 0.8rem; color: #94a3b8; }}
    .main {{ max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem; }}
    .summary-row {{ display: flex; gap: 1.5rem; flex-wrap: wrap; align-items: center;
                   margin-bottom: 2rem; }}
    .donut-wrap {{ background: #fff; border-radius: 12px; padding: 1.25rem;
                  box-shadow: 0 1px 4px rgba(0,0,0,.08); }}
    .stat-cards {{ display: flex; gap: 1rem; flex-wrap: wrap; flex: 1; }}
    .card {{ background: #fff; border-radius: 12px; padding: 1.25rem 1.5rem;
            box-shadow: 0 1px 4px rgba(0,0,0,.08); min-width: 110px; flex: 1; }}
    .card .label {{ font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.06em;
                   color: #64748b; margin-bottom: 0.3rem; }}
    .card .value {{ font-size: 2rem; font-weight: 700; line-height: 1; }}
    .card.c-total .value {{ color: #1e293b; }}
    .card.c-pass  .value {{ color: #16a34a; }}
    .card.c-fail  .value {{ color: #dc2626; }}
    .card.c-error .value {{ color: #9333ea; }}
    .card.c-skip  .value {{ color: #d97706; }}
    .card.c-time  .value {{ color: #0284c7; font-size: 1.5rem; }}
    .overall-banner {{ border-radius: 8px; padding: 0.6rem 1rem; font-size: 0.9rem;
                       font-weight: 600; margin-bottom: 1.5rem; display: inline-block; }}
    .overall-pass {{ background: #dcfce7; color: #15803d; }}
    .overall-fail {{ background: #fee2e2; color: #b91c1c; }}
    .filter-bar {{ display: flex; gap: 0.5rem; margin-bottom: 1rem; flex-wrap: wrap; }}
    .filter-btn {{ padding: 0.35rem 0.9rem; border-radius: 999px; border: 1.5px solid #e2e8f0;
                  background: #fff; cursor: pointer; font-size: 0.82rem; font-weight: 500;
                  transition: all 0.15s; }}
    .filter-btn:hover {{ border-color: #94a3b8; }}
    .filter-btn.active {{ background: #1e293b; color: #fff; border-color: #1e293b; }}
    .table-wrap {{ background: #fff; border-radius: 12px;
                  box-shadow: 0 1px 4px rgba(0,0,0,.08); overflow: hidden; }}
    table {{ width: 100%; border-collapse: collapse; }}
    th {{ background: #f1f5f9; color: #475569; font-size: 0.75rem; text-transform: uppercase;
         letter-spacing: 0.05em; padding: 0.65rem 1rem; text-align: left; font-weight: 600; }}
    td {{ padding: 0.55rem 1rem; border-top: 1px solid #f1f5f9; font-size: 0.875rem;
         vertical-align: top; }}
    tr.group-header td {{ background: #f8fafc; font-size: 0.8rem; color: #475569;
                          padding: 0.4rem 1rem; border-top: 2px solid #e2e8f0; }}
    tr.group-header.fail td {{ border-left: 3px solid #ef4444; }}
    tr.group-header.pass td {{ border-left: 3px solid #22c55e; }}
    .group-counts {{ margin-left: 0.75rem; font-weight: 400; }}
    .badge {{ display: inline-block; padding: 0.15rem 0.55rem; border-radius: 999px;
             font-size: 0.7rem; font-weight: 700; letter-spacing: 0.04em; }}
    .ok   {{ background: #dcfce7; color: #166534; }}
    .bad  {{ background: #fee2e2; color: #991b1b; }}
    .warn {{ background: #fef9c3; color: #854d0e; }}
    .test-name {{ font-size: 0.82rem; }}
    .time-cell {{ color: #64748b; white-space: nowrap; font-size: 0.8rem; }}
    details summary {{ cursor: pointer; color: #3b82f6; font-size: 0.78rem; margin-top: 0.2rem; }}
    .detail-pre {{ white-space: pre-wrap; font-size: 0.75rem; background: #fafafa;
                  border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem;
                  margin-top: 0.4rem; max-height: 300px; overflow-y: auto; color: #374151; }}
    .row-fail {{ background: #fff8f8; }}
    .hidden {{ display: none !important; }}
    .expand-link {{ font-size: 0.78rem; color: #3b82f6; cursor: pointer;
                   text-decoration: underline; margin-bottom: 0.75rem; display: inline-block; }}
  </style>
</head>
<body>
  <div class="topbar">
    <h1>LD Expert Bird — QA Test Report</h1>
    <span class="meta">{html.escape(suite_name)} &nbsp;·&nbsp; {generated}</span>
  </div>

  <div class="main">
    <div class="summary-row">
      <div class="donut-wrap">{donut}</div>
      <div class="stat-cards">
        <div class="card c-total"><div class="label">Total</div><div class="value">{total_count}</div></div>
        <div class="card c-pass"><div class="label">Passed</div><div class="value">{passed_count}</div></div>
        <div class="card c-fail"><div class="label">Failed</div><div class="value">{failed_count}</div></div>
        <div class="card c-error"><div class="label">Errors</div><div class="value">{error_count}</div></div>
        <div class="card c-skip"><div class="label">Skipped</div><div class="value">{skipped_count}</div></div>
        <div class="card c-time"><div class="label">Duration</div><div class="value">{html.escape(summary.get('time','0'))}s</div></div>
      </div>
    </div>

    <div class="{overall_class} overall-banner">
      {'All tests passed' if (failed_count + error_count) == 0 else f'{failed_count} failed · {error_count} errors' if error_count else f'{failed_count} test(s) failed'}
    </div>

    <div class="filter-bar">
      <button class="filter-btn active" onclick="filter('all', this)">All ({total_count})</button>
      <button class="filter-btn" onclick="filter('passed', this)">Passed ({passed_count})</button>
      <button class="filter-btn" onclick="filter('failed', this)">Failed ({failed_count})</button>
      <button class="filter-btn" onclick="filter('error', this)">Errors ({error_count})</button>
      <button class="filter-btn" onclick="filter('skipped', this)">Skipped ({skipped_count})</button>
    </div>

    <span class="expand-link" onclick="toggleAll()">Expand all failures</span>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th style="width:70px">Status</th>
            <th>Test Name</th>
            <th style="width:80px">Time</th>
            <th>Details</th>
          </tr>
        </thead>
        <tbody id="test-rows">
          {rows_html}
        </tbody>
      </table>
    </div>
  </div>

  <script>
    var allExpanded = false;
    function filter(status, btn) {{
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      var rows = document.querySelectorAll('.test-row');
      var groups = document.querySelectorAll('.group-header');
      rows.forEach(function(r) {{
        if (status === 'all') {{
          r.classList.remove('hidden');
        }} else {{
          var rs = r.getAttribute('data-status');
          var match = (status === 'failed') ? (rs === 'failed' || rs === 'error') : rs === status;
          if (match) r.classList.remove('hidden'); else r.classList.add('hidden');
        }}
      }});
      groups.forEach(function(g) {{
        if (status === 'all') {{
          g.classList.remove('hidden');
        }} else {{
          var next = g.nextElementSibling;
          var hasVisible = false;
          while (next && next.classList.contains('test-row')) {{
            if (!next.classList.contains('hidden')) hasVisible = true;
            next = next.nextElementSibling;
          }}
          if (hasVisible) g.classList.remove('hidden'); else g.classList.add('hidden');
        }}
      }});
    }}
    function toggleAll() {{
      allExpanded = !allExpanded;
      document.querySelectorAll('details').forEach(function(d) {{ d.open = allExpanded; }});
      document.querySelector('.expand-link').textContent = allExpanded ? 'Collapse all failures' : 'Expand all failures';
    }}
  </script>
</body>
</html>
"""


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--junit", required=True, type=Path)
    parser.add_argument("--output", required=True, type=Path)
    parser.add_argument("--md", type=Path, help="Optional Markdown report output path")
    args = parser.parse_args()

    if not args.junit.is_file():
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(
            "<html><body><h1>No JUnit file produced</h1>"
            f"<p>Expected: {html.escape(str(args.junit))}</p></body></html>",
            encoding="utf-8",
        )
        if args.md is not None:
            args.md.parent.mkdir(parents=True, exist_ok=True)
            args.md.write_text(
                f"# No JUnit file produced\n\nExpected: `{args.junit}`\n",
                encoding="utf-8",
            )
        return 1

    cases, summary = parse_junit(args.junit)
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(render_html(cases, summary, args.junit), encoding="utf-8")
    if args.md is not None:
        args.md.parent.mkdir(parents=True, exist_ok=True)
        args.md.write_text(render_md(cases, summary, args.junit), encoding="utf-8")
    return 0


if __name__ == "__main__":
    sys.exit(main())
