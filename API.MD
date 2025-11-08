# Terra-Pulse Destination Health API Documentation

## Endpoint Overview

**URL:** `GET /destination/health`
**Purpose:** Comprehensive travel intelligence analysis combining satellite positioning, environmental data, weather conditions, and safety assessments for any geographic location.

### Query Parameters

| Parameter | Type | Required | Description | Example |
|-----------|------|----------|-------------|---------|
| `lat` | float | ✅ | Latitude coordinate (-90 to 90) | `42.40503460923687` |
| `lng` | float | ✅ | Longitude coordinate (-180 to 180) | `19.815049215865674` |

### Example Request
```bash
GET /destination/health?lat=42.40503460923687&lng=19.815049215865674
```

---

## Response Structure

### 🛰️ Position Information
High-precision GPS data from Galileo satellite constellation:

```json
{
  "position_info": {
    "latitude": 42.40503460923687,    // Exact latitude
    "longitude": 19.815049215865674,  // Exact longitude
    "altitude": 351,                  // Elevation in meters above sea level
    "accuracy": 3.74,                 // GPS accuracy in meters (lower = better)
    "satellite_count": 9              // Number of satellites used for positioning
  }
}
```

**Frontend Usage:**
- **Display precise location coordinates** for navigation
- **Show GPS accuracy** as signal strength indicator (< 5m = excellent, < 10m = good)
- **Use altitude** for elevation profiles and terrain analysis
- **Satellite count** indicates positioning reliability (4+ = good, 8+ = excellent)

---

### 🌱 Surface Environmental Indices
Copernicus satellite-derived environmental conditions using spectral analysis:

```json
{
  "surface_indices": {
    "vegetation_index": 0.75,  // 0.0-1.0 (NDVI - Normalized Difference Vegetation Index)
    "water_index": 0.1,        // 0.0-1.0 (NDWI - Normalized Difference Water Index)
    "snow_index": 0.05         // 0.0-1.0 (NDSI - Normalized Difference Snow Index)
  }
}
```

**Frontend Visualization:**
- **Vegetation (0.75)**: 🟢 **Excellent vegetation** - Ideal for hiking, camping, nature activities
- **Water (0.1)**: 🟡 **Minimal water presence** - Low flood risk, dry conditions
- **Snow (0.05)**: 🟢 **Minimal snow coverage** - Safe for winter travel

**Color Coding Suggestions:**
```javascript
const getIndexColor = (value) => {
  if (value >= 0.7) return 'bg-green-500'      // High/Good
  if (value >= 0.3) return 'bg-yellow-500'     // Medium
  return 'bg-red-500'                          // Low/Concerning
}
```

---

### 🌤️ Weather Snapshot
Real-time weather data and comprehensive atmospheric conditions:

```json
{
  "weather_snapshot": {
    "location": {
      "name": "Ragam",              // Nearest populated place
      "region": "Kukes",            // Administrative region/state
      "country": "Albania",         // Country name
      "lat": 42.4114,              // Weather station coordinates
      "lon": 19.84,
      "localtime": "2025-11-08 19:06"  // Local time at location
    },
    "temperature": 13.2,            // Temperature in Celsius
    "condition": "Partly cloudy",   // Weather condition description
    "uv": 0,                       // UV index (0-11+)
    "precip_mm": 0.12,             // Precipitation in millimeters
    "humidity": 94,                // Relative humidity percentage
    "cloud_cover": 75,             // Cloud coverage percentage
    "visibility_km": 10,           // Visibility in kilometers
    "wind_kph": 5,                 // Wind speed in km/h
    "wind_dir": "NE",              // Wind direction (compass)
    "air_quality": {
      "pm2_5": 6.55,               // PM2.5 particulate matter (μg/m³)
      "pm10": 7.35                 // PM10 particulate matter (μg/m³)
    },
    "last_updated": "2025-11-08 19:00"  // Last weather update timestamp
  }
}
```

**Frontend Weather Cards:**
```javascript
// Temperature interpretation
const getTempStatus = (temp) => {
  if (temp < 0) return { status: '🥶 Freezing', color: 'text-blue-600' }
  if (temp < 10) return { status: '❄️ Cold', color: 'text-blue-400' }
  if (temp < 25) return { status: '😊 Comfortable', color: 'text-green-500' }
  if (temp < 35) return { status: '🌞 Warm', color: 'text-yellow-500' }
  return { status: '🔥 Hot', color: 'text-red-500' }
}

// UV Index interpretation
const getUVStatus = (uv) => {
  if (uv <= 2) return '🟢 Low'
  if (uv <= 5) return '🟡 Moderate'
  if (uv <= 7) return '🟠 High'
  if (uv <= 10) return '🔴 Very High'
  return '🟣 Extreme'
}

// Air Quality interpretation
const getAirQuality = (pm25) => {
  if (pm25 <= 12) return { status: '🟢 Good', color: 'text-green-500' }
  if (pm25 <= 35) return { status: '🟡 Moderate', color: 'text-yellow-500' }
  if (pm25 <= 55) return { status: '🟠 Unhealthy for Sensitive', color: 'text-orange-500' }
  return { status: '🔴 Unhealthy', color: 'text-red-500' }
}
```

---

### 📊 Destination Health Score
Comprehensive health assessment (0-100 scale) with detailed component breakdown:

```json
{
  "destination_health_score": 72.75,
  "health_components": {
    "vegetation_contribution": 37.5,     // Vegetation health impact (+/- points)
    "water_contribution": -3,            // Water hazards impact
    "snow_contribution": -1,             // Snow/ice risks impact
    "precip_contribution": -3,           // Precipitation impact
    "visibility_contribution": 15,       // Atmospheric visibility impact
    "air_quality_contribution": 0,       // Air pollution impact
    "uv_extreme_contribution": 0,        // UV radiation risk impact
    "night_travel_contribution": 0       // Nighttime travel safety impact
  }
}
```

**Score Interpretation for Frontend:**

| Score Range | Status | Color | Description |
|-------------|--------|--------|-------------|
| 80-100 | 🟢 EXCELLENT | `text-green-600` | Ideal conditions, perfect for all activities |
| 60-79 | 🟡 GOOD | `text-green-500` | Good conditions with minor considerations |
| 40-59 | 🟠 MODERATE | `text-yellow-500` | Acceptable with precautions |
| 20-39 | 🔴 POOR | `text-orange-500` | Challenging conditions, high precautions |
| 0-19 | ⚫ DANGEROUS | `text-red-600` | Avoid travel, extreme risks |

**Component Visualization:**
```jsx
const HealthComponent = ({ name, value, description }) => (
  <div className="flex justify-between items-center p-2 border-b">
    <span className="font-medium">{name}</span>
    <div className="flex items-center gap-2">
      <span className={`font-bold ${value >= 0 ? 'text-green-600' : 'text-red-600'}`}>
        {value > 0 ? '+' : ''}{value}
      </span>
      <span className="text-sm text-gray-500">{description}</span>
    </div>
  </div>
)
```

**Current Example Analysis:**
- ✅ **Excellent vegetation** (+37.5) - Perfect for outdoor recreation
- ✅ **Great visibility** (+15) - Clear conditions for navigation
- ⚠️ **Minor water concerns** (-3) - Slight moisture, manageable
- ⚠️ **Light precipitation** (-3) - Minor weather impact
- ⚠️ **Minimal snow** (-1) - Very safe winter conditions
- ➖ **Neutral air quality & UV** (0) - Standard levels

---

### 🛡️ Safety Analysis & Recommendations

```json
{
  "route_safety_rating": "HIGH",
  "safety_recommendations": {
    "risk_level": "LOW",
    "recommendations": [
      "Good conditions for travel and outdoor activities",
      "Safe conditions - enjoy your activities",
      "Carry emergency communication device",
      "Check local weather forecast before departure",
      "Inform someone of your travel plans"
    ],
    "priority_alerts": [],
    "equipment_suggestions": [
      "GPS device or smartphone with offline maps",
      "First aid kit"
    ],
    "best_travel_times": [
      "Early morning or late afternoon for best visibility"
    ]
  }
}
```

**Safety Rating Interpretation:**

| Rating | Risk Level | Color | Travel Status |
|--------|------------|--------|---------------|
| HIGH | LOW | 🟢 `bg-green-100 text-green-800` | Safe for all travel modes |
| MEDIUM | MEDIUM | 🟡 `bg-yellow-100 text-yellow-800` | Standard precautions needed |
| LOW | HIGH | 🔴 `bg-red-100 text-red-800` | Restricted travel, high caution |

**Frontend Safety Components:**

```jsx
const SafetyDashboard = ({ safety }) => (
  <div className="space-y-4">
    {/* Risk Level Badge */}
    <div className={`inline-flex px-3 py-1 rounded-full text-sm font-medium ${getRiskBadge(safety.risk_level)}`}>
      Risk Level: {safety.risk_level}
    </div>

    {/* Priority Alerts */}
    {safety.priority_alerts.length > 0 && (
      <div className="bg-red-50 border-l-4 border-red-400 p-4">
        <h4 className="font-bold text-red-800 mb-2">⚠️ Priority Alerts</h4>
        <ul className="space-y-1">
          {safety.priority_alerts.map((alert, i) => (
            <li key={i} className="text-red-700 text-sm">• {alert}</li>
          ))}
        </ul>
      </div>
    )}

    {/* General Recommendations */}
    <div className="bg-blue-50 p-4 rounded-lg">
      <h4 className="font-semibold text-blue-900 mb-2">📋 Recommendations</h4>
      <ul className="space-y-1">
        {safety.recommendations.map((rec, i) => (
          <li key={i} className="text-blue-800 text-sm">• {rec}</li>
        ))}
      </ul>
    </div>

    {/* Equipment Suggestions */}
    <div className="bg-gray-50 p-4 rounded-lg">
      <h4 className="font-semibold text-gray-900 mb-2">🎒 Suggested Equipment</h4>
      <div className="flex flex-wrap gap-2">
        {safety.equipment_suggestions.map((item, i) => (
          <span key={i} className="px-2 py-1 bg-white rounded text-sm border">
            {item}
          </span>
        ))}
      </div>
    </div>

    {/* Best Travel Times */}
    <div className="bg-green-50 p-4 rounded-lg">
      <h4 className="font-semibold text-green-900 mb-2">⏰ Optimal Travel Times</h4>
      <ul className="space-y-1">
        {safety.best_travel_times.map((time, i) => (
          <li key={i} className="text-green-800 text-sm">• {time}</li>
        ))}
      </ul>
    </div>
  </div>
)
```

---

### 🖼️ Satellite Imagery & Analysis

```json
{
  "image_url": "http://cassiehackathon.test/storage/copernicus/copernicus_1762625199.png",
  "url_analizer": "http://cassiehackathon.test/storage/copernicus/copernicus_1762625202.png",
  "bbox": [
    19.805049215865672,  // West longitude
    42.41503460923687,   // North latitude
    19.825049215865675,  // East longitude
    42.39503460923687    // South latitude
  ]
}
```

**Frontend Image Display:**
```jsx
const SatelliteImagery = ({ imageUrl, analyzerUrl, bbox }) => (
  <div className="space-y-4">
    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
      {/* Raw Satellite Image */}
      <div className="space-y-2">
        <h4 className="font-semibold">📸 Satellite Image</h4>
        <img
          src={imageUrl}
          alt="Satellite imagery of location"
          className="w-full rounded-lg shadow-md"
        />
        <p className="text-sm text-gray-600">Raw satellite view of the area</p>
      </div>

      {/* Analysis Overlay */}
      <div className="space-y-2">
        <h4 className="font-semibold">🔬 Environmental Analysis</h4>
        <img
          src={analyzerUrl}
          alt="Environmental analysis overlay"
          className="w-full rounded-lg shadow-md"
        />
        <p className="text-sm text-gray-600">
          Processed analysis showing vegetation, water, and surface conditions
        </p>
      </div>
    </div>

    {/* Bounding Box Info */}
    <div className="text-xs text-gray-500">
      <p>Analysis area: {bbox[1].toFixed(4)}°N, {bbox[0].toFixed(4)}°E to {bbox[3].toFixed(4)}°N, {bbox[2].toFixed(4)}°E</p>
    </div>
  </div>
)
```

---

### ⏰ Data Validity & Caching

```json
{
  "timestamp": "2025-11-08T18:06:42+00:00",
  "valid_until": "2025-11-10T18:06:42+00:00"
}
```

**Frontend Caching Strategy:**
```javascript
const isDataStale = (validUntil) => {
  return new Date() > new Date(validUntil)
}

const formatDataAge = (timestamp) => {
  const age = Date.now() - new Date(timestamp).getTime()
  const minutes = Math.floor(age / 60000)
  const hours = Math.floor(minutes / 60)

  if (hours > 0) return `${hours}h ${minutes % 60}m ago`
  return `${minutes}m ago`
}

// Usage in component
const DataFreshness = ({ timestamp, validUntil }) => (
  <div className="flex items-center gap-2 text-sm text-gray-500">
    <span>📅 Updated: {formatDataAge(timestamp)}</span>
    {isDataStale(validUntil) && (
      <span className="px-2 py-1 bg-yellow-100 text-yellow-800 rounded text-xs">
        ⚠️ Data may be outdated
      </span>
    )}
  </div>
)
```

---

## Complete Frontend Implementation Example

```jsx
const DestinationHealth = ({ lat, lng }) => {
  const [data, setData] = useState(null)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)

  const fetchDestinationHealth = async () => {
    setLoading(true)
    setError(null)

    try {
      const response = await fetch(
        `/destination/health?lat=${lat}&lng=${lng}`
      )

      if (!response.ok) throw new Error('Failed to fetch data')

      const result = await response.json()
      setData(result)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }

  useEffect(() => {
    if (lat && lng) fetchDestinationHealth()
  }, [lat, lng])

  if (loading) return <LoadingSpinner />
  if (error) return <ErrorMessage error={error} />
  if (!data) return null

  return (
    <div className="max-w-4xl mx-auto space-y-6 p-6">
      {/* Header */}
      <div className="text-center space-y-2">
        <h1 className="text-2xl font-bold">Destination Health Analysis</h1>
        <p className="text-gray-600">
          {data.weather_snapshot.location.name}, {data.weather_snapshot.location.country}
        </p>
        <DataFreshness
          timestamp={data.timestamp}
          validUntil={data.valid_until}
        />
      </div>

      {/* Health Score Card */}
      <div className="bg-white rounded-lg shadow-lg p-6">
        <div className="text-center mb-4">
          <div className="text-4xl font-bold mb-2">
            <span className={getHealthScoreColor(data.destination_health_score)}>
              {data.destination_health_score}/100
            </span>
          </div>
          <div className={`inline-flex px-4 py-2 rounded-full ${getHealthScoreBadge(data.destination_health_score)}`}>
            {getHealthScoreStatus(data.destination_health_score)}
          </div>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
          {Object.entries(data.health_components).map(([key, value]) => (
            <HealthComponent
              key={key}
              name={formatComponentName(key)}
              value={value}
              description={getComponentDescription(key)}
            />
          ))}
        </div>
      </div>

      {/* Weather & Environmental Data */}
      <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <WeatherCard weather={data.weather_snapshot} />
        <EnvironmentalIndices indices={data.surface_indices} />
      </div>

      {/* Safety Analysis */}
      <SafetyDashboard safety={data.safety_recommendations} />

      {/* Satellite Imagery */}
      <SatelliteImagery
        imageUrl={data.image_url}
        analyzerUrl={data.url_analizer}
        bbox={data.bbox}
      />

      {/* Position Info */}
      <PositionInfoCard position={data.position_info} />
    </div>
  )
}
```

---

## Error Handling

The API provides graceful fallback responses when external services are unavailable:

```json
{
  "error": "Partial data due to service unavailability",
  "destination_health_score": 50,
  "safety_recommendations": {
    "risk_level": "UNKNOWN",
    "recommendations": [
      "Exercise standard travel caution",
      "Check local weather and road conditions"
    ]
  }
}
```

**Frontend Error Handling:**
```jsx
const ErrorBoundary = ({ error, data }) => {
  if (error && !data) {
    return (
      <div className="bg-red-50 border border-red-200 rounded-lg p-4">
        <h3 className="text-red-800 font-semibold">❌ Service Unavailable</h3>
        <p className="text-red-700">{error}</p>
        <button
          onClick={() => window.location.reload()}
          className="mt-2 px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700"
        >
          Retry
        </button>
      </div>
    )
  }

  if (data?.error) {
    return (
      <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-4">
        <h4 className="text-yellow-800 font-semibold">⚠️ Limited Data Available</h4>
        <p className="text-yellow-700 text-sm">{data.error}</p>
        <p className="text-yellow-600 text-xs mt-1">
          Showing cached/fallback data with reduced accuracy
        </p>
      </div>
    )
  }

  return null
}
```

---

## API Rate Limits & Best Practices

- **Rate Limit**: 60 requests per minute per IP
- **Cache Duration**: 48 hours (use `valid_until` field)
- **Optimal Usage**: Cache responses locally and check `valid_until` before making new requests
- **Error Retry**: Use exponential backoff for failed requests
- **Coordinate Precision**: Use 6-8 decimal places for best accuracy

---

This API provides comprehensive travel intelligence by combining satellite positioning, environmental analysis, weather data, and safety assessments into a single, actionable response perfect for building travel planning and safety applications.
