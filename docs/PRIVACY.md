# Privacy Policy

Last updated: 2026-02-11

This Privacy Policy explains how WeatherNode processes data for:

- **Community telemetry and public station listing** (optional, explicitly enabled by station owners)
- **Website operation** (varies per deployment; e.g. server/security logs)

## 1. Who We Are

WeatherNode is an open-source weather dashboard project.

Important: WeatherNode can be self-hosted. The exact data processing depends on the operator of the specific website you are visiting.

If you are using the official `weathernode.dev` deployment, contact: **info@weathernode.dev**.

## 2. Data We Collect

### 2.1 Community Telemetry (Public by Design)

If community telemetry/public listing is enabled by a station owner, the station metadata is intended to be **public** (for example via a community JSON feed). This is the core data WeatherNode collects for the community map/listing.

Typical fields include:

- station name
- country / location label
- latitude and longitude (exact or rounded, depending on configuration)
- station hardware type
- timestamps (when the station was last seen/updated)

If you enable telemetry, assume this data can be indexed, copied, and redistributed by third parties because it is published openly.

### 2.2 Website Operation (Per Deployment)

Like most websites, the server may process basic operational data to function securely and reliably. Depending on deployment, this may include:

- server logs (e.g. IP address, user agent, request path, referrer), where technically required for security and operations
- error logs and performance data (to diagnose failures and prevent abuse)

Self-hosted installs typically keep such data on the operator’s own infrastructure.

### 2.3 Advertising (Optional)

Some WeatherNode deployments enable third-party advertising in the dashboard.

- If advertising is disabled by the site operator, no ad scripts are loaded.
- If advertising is enabled, third-party ad partners may set cookies or similar identifiers according to their own systems and policies.
- Ad loading behavior is controlled by the site operator via an admin setting:
  - **Auto mode:** visitors in EEA/UK/Switzerland are asked for consent before ad scripts load; outside those regions ads may load immediately.
  - **Always show ads immediately:** ad scripts can load without waiting for ad-consent choice.
  - **Always require consent before ads:** ad scripts are blocked until ad consent is accepted.

When consent is required for ads, visitors can change their ad-consent choice later via the **Cookie settings** link in the footer.

Ad partners (for example Google AdSense, Media.net, Adsterra, or others) are configured by the local site operator and may differ per deployment.

## 3. Why We Process Data

We process data to:

- deliver live weather data and forecasts
- provide community station map/listing functionality (when telemetry is enabled)
- secure and maintain the service
- diagnose errors, abuse, and operational issues

## 4. Legal Basis (EU/EEA)

Processing is based on one or more of:

- **consent** (for optional community telemetry/public listing features)
- **legitimate interest** (service reliability, abuse prevention, and security logging)
- **legal obligation** (where required by law)

## 5. Telemetry Consent and Opt-Out

Community telemetry/public listing is optional and can be disabled in the admin panel.  
If a station owner opts out, publication should stop and removal requests are handled as described below.

## 6. Data Retention

Retention periods depend on data type:

- operational logs: kept only as long as needed for security and troubleshooting
- telemetry/public listing data: kept while feature is enabled and for a short operational period after opt-out/removal requests (and may still exist in third-party copies due to public distribution)
- configuration data: kept while the service is in use

Exact retention may vary by deployment environment.

## 7. Sharing and Processors

Data may be processed by infrastructure providers and service integrations used by the site (hosting, CDN, logging, monitoring, or APIs).  
We do not sell personal data.

## 8. Your Rights

Where applicable (including GDPR), you may request:

- access to your data
- correction of inaccurate data
- deletion of data
- restriction or objection to processing

Submit requests to the site operator for the website you are using, or (for `weathernode.dev`) **info@weathernode.dev**.

## 9. Removal Requests (SLA)

Removal requests are handled as quickly as possible and targeted within **30 days**.  
Urgent or security-related requests are prioritized.

## 10. Security

Reasonable technical and organizational measures are used to protect data, but no system is 100% secure.

## 11. Policy Changes

This policy may be updated over time. The latest version is always published on this page with the updated date.
