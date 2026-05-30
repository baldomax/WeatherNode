#!/usr/bin/env python3

"""Hybrid dashboard Playwright regression check.

This script validates:
1. Hybrid SSR bootstrap globals are present.
2. All planned widget IDs are present in first HTML payload.
3. /api/weather/dashboard is fetched shortly after hydration (no 15s defer).
"""

from __future__ import annotations

import argparse
import json
import sys
import time
from typing import Any

from playwright.sync_api import sync_playwright


WIDGET_IDS = [
    "current",
    "forecast",
    "hourly",
    "wind",
    "pressure",
    "rain",
    "sun_moon",
    "uv_solar",
    "airquality",
    "pollen",
    "tide",
    "metar",
    "earthquakes",
    "alerts",
    "lightning",
    "indoor",
    "extra_temps",
    "soil",
    "pm25",
    "co2",
    "battery",
    "radar",
    "webcam",
    "aurora",
    "astro_events",
    "ads",
]


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(description="Hybrid dashboard Playwright regression check")
    parser.add_argument("--url", default="http://localhost:8000/", help="Dashboard URL")
    parser.add_argument(
        "--chrome-executable",
        default="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome",
        help="Chromium/Chrome executable path",
    )
    parser.add_argument(
        "--headless",
        action="store_true",
        default=True,
        help="Run in headless mode (default: true)",
    )
    return parser.parse_args()


def fail(results: dict[str, Any], message: str) -> int:
    results["ok"] = False
    results["error"] = message
    print(json.dumps(results, indent=2))
    return 1


def main() -> int:
    args = parse_args()
    results: dict[str, Any] = {
        "ok": True,
        "url": args.url,
        "hybrid_flag": None,
        "initial_payload": None,
        "missing_widget_ids": [],
        "requests": {"history": [], "dashboard": []},
        "timing": {},
    }

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            executable_path=args.chrome_executable,
            headless=args.headless,
            args=["--no-sandbox", "--disable-gpu", "--disable-dev-shm-usage"],
        )
        context = browser.new_context()
        page = context.new_page()

        t0 = time.perf_counter()

        def on_request(request: Any) -> None:
            elapsed_ms = round((time.perf_counter() - t0) * 1000)
            url = request.url
            if "/api/weather/history?field=temperature&period=24h" in url:
                results["requests"]["history"].append({"t_ms": elapsed_ms, "url": url})
            if "/api/weather/dashboard" in url:
                results["requests"]["dashboard"].append({"t_ms": elapsed_ms, "url": url})

        page.on("request", on_request)
        page.goto(args.url, wait_until="domcontentloaded", timeout=45000)

        html = page.content()
        results["hybrid_flag"] = page.evaluate("window.__METEO_DASHBOARD_HYBRID__ === true")
        results["initial_payload"] = page.evaluate("!!window.__METEO_DASHBOARD_INITIAL__")

        missing = []
        for widget_id in WIDGET_IDS:
            if f'data-widget="{widget_id}"' not in html:
                missing.append(widget_id)
        results["missing_widget_ids"] = missing

        # Must fetch shortly after hydration (no delayed 15s window).
        page.wait_for_timeout(8000)
        results["timing"]["dashboard_count_after_8s"] = len(results["requests"]["dashboard"])
        first_dashboard = (
            results["requests"]["dashboard"][0]["t_ms"]
            if results["requests"]["dashboard"]
            else None
        )
        results["timing"]["first_dashboard_request_ms"] = first_dashboard

        browser.close()

    if not results["hybrid_flag"]:
        return fail(results, "window.__METEO_DASHBOARD_HYBRID__ is not true")

    if not results["initial_payload"]:
        return fail(results, "window.__METEO_DASHBOARD_INITIAL__ missing")

    if results["missing_widget_ids"]:
        return fail(results, "Missing widget IDs in HTML payload")

    if results["timing"]["dashboard_count_after_8s"] < 1:
        return fail(results, "Dashboard API did not fire within 8s")

    if (
        results["timing"]["first_dashboard_request_ms"] is not None
        and results["timing"]["first_dashboard_request_ms"] >= 10000
    ):
        return fail(results, "Dashboard API first request is too late (>=10s)")

    print(json.dumps(results, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
