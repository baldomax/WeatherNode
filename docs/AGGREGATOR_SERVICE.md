# Central Telemetry Aggregator Service

## Overview

The telemetry system uses a **central aggregator service** that receives station data from all MeteoUitgeest installations and updates the GitHub repository. This approach:

- ✅ **No GitHub tokens needed** on individual sites
- ✅ **Single point of update** - change aggregator code once, all sites benefit
- ✅ **No write conflicts** - aggregator handles GitHub updates sequentially
- ✅ **Better security** - aggregator can validate and sanitize data
- ✅ **Easier maintenance** - update API logic in one place

## Architecture

```
┌─────────────┐         ┌──────────────┐         ┌─────────────┐
│   Site 1    │────────▶│  Aggregator  │────────▶│   GitHub    │
│  (POST)     │         │   Service    │         │  Repository │
└─────────────┘         └──────────────┘         └─────────────┘
┌─────────────┐         │              │         └─────────────┘
│   Site 2    │────────▶│  - Validates │         │  stations.json│
│  (POST)     │         │  - Aggregates│         │               │
└─────────────┘         │  - Updates   │         └─────────────┘
┌─────────────┐         │    GitHub    │
│   Site N    │────────▶│              │
│  (POST)     │         └──────────────┘
└─────────────┘
```

## Aggregator Service Requirements

The aggregator service should:

1. **Accept POST requests** at `/telemetry` endpoint
2. **Validate incoming data** (station ID, name, location, etc.)
3. **Read current stations.json** from GitHub
4. **Add/update/remove** station in the JSON
5. **Write back to GitHub** using a single GitHub token
6. **Return success/error** response

## Example Aggregator Implementation

### Simple PHP/Laravel Aggregator

```php
<?php
// routes/api.php
Route::post('/telemetry', [TelemetryController::class, 'receive']);

// app/Http/Controllers/TelemetryController.php
class TelemetryController extends Controller
{
    public function receive(Request $request)
    {
        // Optional: Validate API key
        $apiKey = $request->header('X-API-Key');
        if (!empty(env('TELEMETRY_API_KEY')) && $apiKey !== env('TELEMETRY_API_KEY')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $action = $request->input('action', 'update');
        $stationData = $request->input('station');
        
        if ($action === 'remove') {
            $stationId = $request->input('station_id');
            return $this->removeStation($stationId);
        }
        
        // Validate station data
        $required = ['id', 'name', 'latitude', 'longitude', 'url'];
        foreach ($required as $field) {
            if (empty($stationData[$field])) {
                return response()->json(['error' => "Missing field: {$field}"], 400);
            }
        }
        
        // Update GitHub
        $githubService = app(GitHubTelemetryService::class);
        $success = $githubService->addOrUpdateStation($stationData);
        
        if ($success) {
            return response()->json(['success' => true, 'message' => 'Station updated']);
        }
        
        return response()->json(['error' => 'Failed to update'], 500);
    }
    
    private function removeStation(string $stationId)
    {
        $githubService = app(GitHubTelemetryService::class);
        $success = $githubService->removeStation($stationId);
        return response()->json(['success' => $success]);
    }
}
```

### Simple Node.js/Express Aggregator

```javascript
const express = require('express');
const axios = require('axios');
const app = express();

app.use(express.json());

const GITHUB_TOKEN = process.env.GITHUB_TOKEN;
const GITHUB_REPO = 'meteouitgeest/community-stations';
const GITHUB_FILE = 'stations.json';

app.post('/telemetry', async (req, res) => {
    try {
        const { action, station, station_id } = req.body;
        
        if (action === 'remove') {
            await removeStation(station_id);
            return res.json({ success: true });
        }
        
        // Validate station data
        if (!station || !station.id || !station.name || !station.latitude || !station.longitude) {
            return res.status(400).json({ error: 'Invalid station data' });
        }
        
        // Read current file
        const currentData = await readGitHubFile();
        const stations = currentData.stations || [];
        
        // Find and update or add
        const index = stations.findIndex(s => s.id === station.id);
        if (index >= 0) {
            stations[index] = station;
        } else {
            stations.push(station);
        }
        
        // Write back
        await writeGitHubFile({
            stations: stations,
            last_updated: new Date().toISOString()
        });
        
        res.json({ success: true });
    } catch (error) {
        console.error('Error:', error);
        res.status(500).json({ error: error.message });
    }
});

async function readGitHubFile() {
    const url = `https://api.github.com/repos/${GITHUB_REPO}/contents/${GITHUB_FILE}`;
    const response = await axios.get(url, {
        headers: {
            'Authorization': `token ${GITHUB_TOKEN}`,
            'Accept': 'application/vnd.github.v3+json'
        }
    });
    
    const content = Buffer.from(response.data.content, 'base64').toString();
    return JSON.parse(content);
}

async function writeGitHubFile(data) {
    const url = `https://api.github.com/repos/${GITHUB_REPO}/contents/${GITHUB_FILE}`;
    
    // Get current SHA
    const current = await axios.get(url, {
        headers: {
            'Authorization': `token ${GITHUB_TOKEN}`,
            'Accept': 'application/vnd.github.v3+json'
        }
    });
    
    const content = Buffer.from(JSON.stringify(data, null, 2)).toString('base64');
    
    await axios.put(url, {
        message: 'Update community stations',
        content: content,
        sha: current.data.sha
    }, {
        headers: {
            'Authorization': `token ${GITHUB_TOKEN}`,
            'Accept': 'application/vnd.github.v3+json'
        }
    });
}

app.listen(3000, () => {
    console.log('Aggregator service running on port 3000');
});
```

## Deployment Options

### Option 1: Deploy on Your Server
- Host the aggregator on `api.meteouitgeest.nl` or similar
- Use your GitHub token (stored securely on server)
- All sites point to this URL

### Option 2: Use a Serverless Function
- Deploy as AWS Lambda, Vercel Function, or similar
- GitHub token stored as environment variable
- Scales automatically

### Option 3: GitHub Actions (Scheduled)
- Sites POST to a webhook endpoint
- Webhook triggers GitHub Action
- Action reads webhook data and updates repository
- More complex but fully automated

## Default Configuration

The default aggregator URL is: `https://api.meteouitgeest.nl/telemetry`

You can change this in Admin → Settings → Community Telemetry if you want to use a different aggregator.

## Security Considerations

1. **API Key Authentication** (Optional)
   - Sites can include an API key in `X-API-Key` header
   - Aggregator validates this before processing

2. **Rate Limiting**
   - Aggregator should implement rate limiting
   - Prevent abuse/spam

3. **Data Validation**
   - Validate latitude/longitude ranges
   - Sanitize station names/URLs
   - Reject invalid data

4. **GitHub Token Security**
   - Store token as environment variable
   - Never commit to repository
   - Use minimal required permissions (repo scope)

## Testing the Aggregator

```bash
# Test adding/updating a station
curl -X POST https://api.meteouitgeest.nl/telemetry \
  -H "Content-Type: application/json" \
  -H "X-API-Key: your-key" \
  -d '{
    "station": {
      "id": "abc123",
      "name": "Test Station",
      "hardware": "WH4000SE",
      "manufacturer": "fineoffset",
      "latitude": 52.5164,
      "longitude": 4.7079,
      "url": "https://example.com",
      "updated_at": "2026-01-14T12:00:00Z"
    }
  }'

# Test removing a station
curl -X POST https://api.meteouitgeest.nl/telemetry \
  -H "Content-Type: application/json" \
  -d '{
    "action": "remove",
    "station_id": "abc123"
  }'
```
