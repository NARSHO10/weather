<?php
$weather = null;
$error = null;

// Load local .env (for local development) if OPENWEATHER_API_KEY is not set
if (!getenv('OPENWEATHER_API_KEY') && file_exists(__DIR__ . '/.env')) {
    $env = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($env as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($k,$v) = array_map('trim', explode('=', $line, 2));
        if ($k && !getenv($k)) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

if(isset($_GET['city'])){
    $city_raw = trim($_GET['city']);
    if($city_raw === ''){
        $error = 'Please enter a city name.';
    } else {
        $city = htmlspecialchars($city_raw);
        // Read API key from environment for security (set OPENWEATHER_API_KEY in Render / env)
        $apiKey = getenv('OPENWEATHER_API_KEY');
        if(!$apiKey){
            $error = 'Server configuration error: OPENWEATHER_API_KEY is not set. Please add the API key to your environment variables.';
        } else {
            $url = "https://api.openweathermap.org/data/2.5/weather?q=" . urlencode($city_raw) . "&appid=" . $apiKey . "&units=metric";

            $response = @file_get_contents($url);
            if($response !== false){
                $data = json_decode($response , true);
                if(isset($data['cod']) && $data['cod'] == 200){
                    $weather = [
                        'city' => $data['name'],
                        'temp' => $data['main']['temp'],
                        'desc' => ucfirst($data['weather'][0]['description']),
                        'icon' => $data['weather'][0]['icon'],
                        'lat' => isset($data['coord']['lat']) ? $data['coord']['lat'] : null,
                        'lon' => isset($data['coord']['lon']) ? $data['coord']['lon'] : null,
                    ];
                }else{
                    $msg = isset($data['message']) ? ucfirst($data['message']) : 'City not found';
                    $error = $msg . ' — check spelling or try "City,CountryCode" (e.g., "London,GB").';
                }
            }else{
                $error = 'Unable to fetch weather data. Please try again later.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta charset="UTF-8">
    <title>Weather App</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    <!-- Leaflet CSS & JS (for responsive interactive maps) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        /* Responsive map container: adjusts height to viewport while staying usable on small screens */
        .map-container{ width:100%; height:40vh; min-height:220px; max-height:600px; border-radius:8px; overflow:hidden; margin-top:0.5rem; }
        @media (max-width:576px){ .map-container{ height:32vh; min-height:180px; } }
        body{
            min-height:100vh;
            background-image: url('https://static.vecteezy.com/system/resources/thumbnails/038/563/640/small_2x/ai-generated-the-sky-was-dark-with-pouring-rain-accompanied-by-nimbostratus-clouds-that-covered-the-entire-sky-photo.jpg');
            background-size:cover;
            background-position:center;
            background-repeat:no-repeat;
            background-attachment:fixed;
            background-color:#1f2b34;
            color:#fff;
        }
        body::before{
            content:'';
            position:fixed;
            inset:0;
            background:rgba(0,0,0,0.45);
            z-index:0;
        }
        .container{position:relative;z-index:1;}
        .card{
            background: linear-gradient(180deg, rgba(255,255,255,0.98), rgba(250,250,250,0.95));
            border-radius:12px;
            box-shadow:0 8px 30px rgba(0,0,0,0.35);
            color:#0b1b23;
            overflow:hidden;
        }
        .card .card-body{
            background:transparent;
            color:#0b1b23;
            padding:1.75rem;
        }
        .card .card-title{
            color:#07202a;
            font-weight:700;
            margin-bottom:0.5rem;
        }
        .card p.text-muted{
            color:#4b5563 !important;
        }
    </style>
</head>
<body>
    <div class='container py-5'>
        <h1 class='text-center mb-4'>Weather App </h1>
        <form method='get' class='row g-2 justify-content-center mb-4'>
            <div class='col-12 col-md-6'>
                <input type='text' name='city' id='cityInput' class='form-control' placeholder='Enter city name' required>
            </div>
            <div class='col-12 col-md-auto d-grid'>
                <button type='submit' class='btn btn-primary w-100 w-md-auto'>Get weather</button>
            </div>
        </form>
        <div id="result" style="display:none;">
        <?php if($weather): ?>
           <div class='card mx-auto' style='max-width: 900px; background-image: url(https://images.hdqwalls.com/wallpapers/clouds-summer-weather-5k-1b.jpg); position: relative; background-size:cover; background-repeat:no-repeat; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.35);'>
                <div class='row g-0 align-items-center'>
                    <div class='col-md-5 d-flex justify-content-center p-3'>
                        <div class='text-center'>
                            <h3 class='card-title mb-1'><?=$weather['city']; ?></h3>
                            <img src="https://openweathermap.org/img/wn/<?=$weather['icon']; ?>@2x.png" alt="weather icon">
                            <h4 class='mt-2'><?=$weather['temp']; ?> °C</h4>
                            <p class='text-muted'><?=$weather['desc']; ?></p>
                        </div>
                    </div>
                    <div class='col-md-7'>
                        <div id="map" class="map-container"></div>
                    </div>
                </div>
            </div>
            <div class='text-center mt-2'>
                <?php if(!empty($weather['lat']) && !empty($weather['lon'])): ?>
                    <a href="https://www.openstreetmap.org/?mlat=<?=$weather['lat'];?>&mlon=<?=$weather['lon'];?>#map=12/<?=$weather['lat'];?>/<?=$weather['lon'];?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Open in OpenStreetMap</a>
                <?php endif; ?>
            </div>
        <?php elseif($error): ?>
            <div class='alert alert-danger d-flex align-items-center' role='alert'>
                <span class='fs-4 me-2'>⚠️</span>
                <div class='flex-grow-1'><?=$error; ?></div>
                <button id='retryBtn' type='button' class='btn btn-outline-dark btn-sm ms-3'>Try again</button>
            </div>
        <?php endif; ?>
        </div>
        <?php if($weather || $error): ?>
        <script>
        document.addEventListener('DOMContentLoaded', function(){
            var r = document.getElementById('result');
            var input = document.getElementById('cityInput');
            if(r) {
                r.style.display = 'block';
                r.style.opacity = 0;
                r.style.transition = 'opacity 300ms ease';
                requestAnimationFrame(function(){ r.style.opacity = 1 });
                r.scrollIntoView({behavior:'smooth', block:'center'});
            }
            if(input) input.blur();
            var retry = document.getElementById('retryBtn');
            if(retry){
                retry.addEventListener('click', function(){
                    r.style.display = 'none';
                    r.style.opacity = '';
                    input && (input.focus(), input.select());
                });
            } else {
                if(input) input.focus();
            }

            // Initialize map when coordinates are available from server-side response
            <?php if($weather && !empty($weather['lat']) && !empty($weather['lon'])): ?>
            (function(){
                try{
                    var lat = <?= json_encode($weather['lat']); ?>;
                    var lon = <?= json_encode($weather['lon']); ?>;
                    var popupContent = <?= json_encode($weather['city'] . "<br>" . $weather['temp'] . " °C — " . $weather['desc']); ?>;
                    var mapEl = document.getElementById('map');
                    if(mapEl){
                        var map = L.map('map', {scrollWheelZoom: false}).setView([lat, lon], 10);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap contributors'
                        }).addTo(map);
                        L.marker([lat, lon]).addTo(map).bindPopup(popupContent).openPopup();
                        // Force map to render properly after CSS transitions
                        setTimeout(function(){ map.invalidateSize(); }, 200);
                    }
                }catch(e){ console.warn('Map init failed', e); }
            })();
            <?php endif; ?>
        });
        </script>
        <?php endif; ?>
    </div>

</body>