<?php
// Database configuration from environment variables - with fallbacks and debug
$dbHost = getenv('DB_HOST') ?: (getenv('MYSQLHOST') ?: 'railway');
$dbPort = getenv('DB_PORT') ?: (getenv('MYSQLPORT') ?: '3306');
$dbName = getenv('DB_NAME') ?: (getenv('MYSQLDATABASE') ?: 'user_visits');
$dbUser = getenv('DB_USER') ?: (getenv('MYSQLUSER') ?: 'root');
$dbPass = getenv('DB_PASSWORD') ?: (getenv('MYSQLPASSWORD') ?: 'hCwShBsncMEStlTvvyToWLXEUSmZFGoJ');

// DEBUG INFO COLLECTOR
$debugInfo = [
    'DB_HOST_ENV' => getenv('DB_HOST'),
    'MYSQLHOST_ENV' => getenv('MYSQLHOST'),
    'FINAL_HOST' => $dbHost,
    'DB_PORT_ENV' => getenv('DB_PORT'),
    'FINAL_PORT' => $dbPort,
    'DB_NAME_ENV' => getenv('DB_NAME'),
    'FINAL_NAME' => $dbName,
    'DB_USER_ENV' => getenv('DB_USER'),
    'FINAL_USER' => $dbUser,
    // WARNING: Showing password for debugging purposes as requested
    'DB_PASS_ENV_SET' => getenv('DB_PASSWORD') ? 'YES' : 'NO',
    'FINAL_PASS' => $dbPass
];

// Function to get real IP address
function getRealIpAddress()
{
    $ipHeaders = [
        'HTTP_CF_CONNECTING_IP', // Cloudflare
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR'
    ];

    foreach ($ipHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Handle comma-separated IPs (X-Forwarded-For can contain multiple IPs)
            if (strpos($ip, ',') !== false) {
                $ips = explode(',', $ip);
                $ip = trim($ips[0]);
            }
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }

    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

// Function to detect potential VPN/Proxy usage
function detectVpnProxy()
{
    $indicators = [];

    // Check for common proxy headers
    $proxyHeaders = [
        'HTTP_VIA',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_FORWARDED',
        'HTTP_CLIENT_IP',
        'HTTP_PROXY_CONNECTION'
    ];

    foreach ($proxyHeaders as $header) {
        if (!empty($_SERVER[$header])) {
            $indicators[] = $header;
        }
    }

    // Check for Tor exit nodes (basic check)
    if (!empty($_SERVER['HTTP_X_TOR_EXIT_NODE'])) {
        $indicators[] = 'TOR_DETECTED';
    }

    // Check for common VPN ports in headers
    if (!empty($_SERVER['HTTP_X_FORWARDED_PORT'])) {
        $port = $_SERVER['HTTP_X_FORWARDED_PORT'];
        if (in_array($port, ['1194', '1723', '500', '4500'])) {
            $indicators[] = 'VPN_PORT_DETECTED';
        }
    }

    return [
        'is_likely_proxy' => count($indicators) > 0,
        'indicators' => implode(', ', $indicators),
        'confidence' => count($indicators) > 2 ? 'high' : (count($indicators) > 0 ? 'medium' : 'low')
    ];
}

// Function to parse User-Agent
function parseUserAgent($userAgent)
{
    $browser = 'Unknown';
    $os = 'Unknown';
    $device = 'Desktop';

    // Detect OS
    if (preg_match('/windows/i', $userAgent)) {
        $os = 'Windows';
    } elseif (preg_match('/macintosh|mac os x/i', $userAgent)) {
        $os = 'macOS';
    } elseif (preg_match('/linux/i', $userAgent)) {
        $os = 'Linux';
    } elseif (preg_match('/android/i', $userAgent)) {
        $os = 'Android';
        $device = 'Mobile';
    } elseif (preg_match('/iphone|ipad|ipod/i', $userAgent)) {
        $os = 'iOS';
        $device = preg_match('/ipad/i', $userAgent) ? 'Tablet' : 'Mobile';
    }

    // Detect Browser
    if (preg_match('/edg/i', $userAgent)) {
        $browser = 'Edge';
    } elseif (preg_match('/chrome/i', $userAgent)) {
        $browser = 'Chrome';
    } elseif (preg_match('/safari/i', $userAgent)) {
        $browser = 'Safari';
    } elseif (preg_match('/firefox/i', $userAgent)) {
        $browser = 'Firefox';
    } elseif (preg_match('/opera|opr/i', $userAgent)) {
        $browser = 'Opera';
    } elseif (preg_match('/msie|trident/i', $userAgent)) {
        $browser = 'Internet Explorer';
    }

    // Detect device type
    if (preg_match('/mobile/i', $userAgent) && $device === 'Desktop') {
        $device = 'Mobile';
    } elseif (preg_match('/tablet/i', $userAgent)) {
        $device = 'Tablet';
    }

    return [
        'browser' => $browser,
        'os' => $os,
        'device' => $device
    ];
}

// Function to get IP details from ip-api.com
function getIpDetails($ip)
{
    // Use a public API to get IP details
    // Note: ip-api.com is free for non-commercial use, limited to 45 requests per minute
    $apiUrl = "http://ip-api.com/json/{$ip}?fields=status,message,country,regionName,city,lat,lon,isp,org";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        return json_decode($response, true);
    }

    return null;
}

// Collect all data
$realIp = getRealIpAddress();
$directIp = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$vpnDetection = detectVpnProxy();
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
$parsedUA = parseUserAgent($userAgent);
$referrer = $_SERVER['HTTP_REFERER'] ?? 'Direct';
$language = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'Unknown';
$requestMethod = $_SERVER['REQUEST_METHOD'];
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'HTTPS' : 'HTTP';
$port = $_SERVER['SERVER_PORT'] ?? '80';

// Get IP Details
$ipDetails = getIpDetails($realIp);
$isp = $ipDetails['isp'] ?? 'Unknown';
$city = $ipDetails['city'] ?? 'Unknown';
$region = $ipDetails['regionName'] ?? 'Unknown';
$country = $ipDetails['country'] ?? 'Unknown';
$lat = $ipDetails['lat'] ?? 0.0;
$lon = $ipDetails['lon'] ?? 0.0;
$org = $ipDetails['org'] ?? 'Unknown';

// Get screen resolution and other client-side data from POST (if available)
$screenWidth = $_POST['screen_width'] ?? null;
$screenHeight = $_POST['screen_height'] ?? null;
$timezone = $_POST['timezone'] ?? null;

try {
    // Connect to database
    $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);

    // Insert data into database
    $sql = "INSERT INTO user_visits (
        ip_address,
        real_ip,
        is_likely_vpn,
        vpn_indicators,
        vpn_confidence,
        user_agent,
        browser,
        operating_system,
        device_type,
        referrer,
        language,
        screen_width,
        screen_height,
        timezone,
        request_method,
        request_uri,
        protocol,
        port,
        isp,
        city,
        region,
        country,
        lat,
        lon,
        org
    ) VALUES (
        :ip_address,
        :real_ip,
        :is_likely_vpn,
        :vpn_indicators,
        :vpn_confidence,
        :user_agent,
        :browser,
        :operating_system,
        :device_type,
        :referrer,
        :language,
        :screen_width,
        :screen_height,
        :timezone,
        :request_method,
        :request_uri,
        :protocol,
        :port,
        :isp,
        :city,
        :region,
        :country,
        :lat,
        :lon,
        :org
    )";
    // print_r($sql); // Commented out to reduce noise
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':ip_address' => $directIp,
        ':real_ip' => $realIp,
        ':is_likely_vpn' => $vpnDetection['is_likely_proxy'] ? 1 : 0,
        ':vpn_indicators' => $vpnDetection['indicators'],
        ':vpn_confidence' => $vpnDetection['confidence'],
        ':user_agent' => $userAgent,
        ':browser' => $parsedUA['browser'],
        ':operating_system' => $parsedUA['os'],
        ':device_type' => $parsedUA['device'],
        ':referrer' => $referrer,
        ':language' => $language,
        ':screen_width' => $screenWidth,
        ':screen_height' => $screenHeight,
        ':timezone' => $timezone,
        ':request_method' => $requestMethod,
        ':request_uri' => $requestUri,
        ':protocol' => $protocol,
        ':port' => $port,
        ':isp' => $isp,
        ':city' => $city,
        ':region' => $region,
        ':country' => $country,
        ':lat' => $lat,
        ':lon' => $lon,
        ':org' => $org
    ]);

    $insertId = $pdo->lastInsertId();
    $success = true;

} catch (PDOException $e) {
    $success = false;
    $errorMessage = $e->getMessage() . " (Host: $dbHost, Port: $dbPort)";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Data Collection</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 40px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }

        .status {
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            font-weight: 500;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .info-grid {
            display: grid;
            gap: 15px;
            margin-top: 20px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }

        .info-label {
            font-weight: 600;
            color: #555;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .info-value {
            color: #333;
            font-size: 16px;
            word-break: break-all;
        }

        .vpn-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 10px;
        }

        .vpn-yes {
            background: #fff3cd;
            color: #856404;
        }

        .vpn-no {
            background: #d4edda;
            color: #155724;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }
    </style>
</head>

<body>
    <div class="container" style="display: none;">
        <h1>🔍 User Data Collection</h1>

        <?php if ($success): ?>
            <div class="status success">
                ✅ Your visit has been recorded successfully! (ID: <?php echo htmlspecialchars($insertId); ?>)
            </div>

            <!-- DEBUG INFO -->
            <div class="info-grid" style="margin-bottom: 20px;">
                <div class="info-item" style="border-left-color: #ffc107; background: #fff3cd;">
                    <div class="info-label">⚠️ DEBUG INFO (REMOVE IN PRODUCTION)</div>
                    <div class="info-value" style="font-family: monospace; font-size: 12px;">
                        <strong>Host:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_HOST']); ?> (Env:
                        <?php echo htmlspecialchars($debugInfo['DB_HOST_ENV'] ?? 'NULL'); ?> /
                        <?php echo htmlspecialchars($debugInfo['MYSQLHOST_ENV'] ?? 'NULL'); ?>)<br>
                        <strong>Port:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_PORT']); ?><br>
                        <strong>DB:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_NAME']); ?><br>
                        <strong>User:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_USER']); ?><br>
                        <strong>Pass:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_PASS']); ?>
                    </div>
                </div>
            </div>

            <div class="info-grid">
                <div class=" info-item">
                    <div class="info-label">IP Address</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($directIp); ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Real IP (Behind Proxy)</div>
                    <div class="info-value"><?php echo htmlspecialchars($realIp); ?></div>
                </div>

                <div class="info-item">
                    <div class="info-label">ISP & Organization</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($isp); ?>
                        <?php if ($org && $org !== $isp): ?>
                            <br><small style="color: #666;"><?php echo htmlspecialchars($org); ?></small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Location</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($city); ?>, <?php echo htmlspecialchars($region); ?><br>
                        <?php echo htmlspecialchars($country); ?>
                        <?php if ($lat && $lon): ?>
                            <br><small style="color: #666;">
                                <a href="https://www.google.com/maps?q=<?php echo $lat; ?>,<?php echo $lon; ?>" target="_blank"
                                    style="color: #667eea; text-decoration: none;">
                                    📍 View on Map (<?php echo $lat; ?>, <?php echo $lon; ?>)
                                </a>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">VPN/Proxy Detection</div>
                    <div class="info-value">
                        <?php echo $vpnDetection['is_likely_proxy'] ? 'Likely Using VPN/Proxy' : 'Direct Connection'; ?>
                        <span class="vpn-badge <?php echo $vpnDetection['is_likely_proxy'] ? 'vpn-yes' : 'vpn-no'; ?>">
                            <?php echo strtoupper($vpnDetection['confidence']); ?> CONFIDENCE
                        </span>
                        <?php if ($vpnDetection['indicators']): ?>
                            <br><small style="color: #666;">Indicators:
                                <?php echo htmlspecialchars($vpnDetection['indicators']); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Device Information</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($parsedUA['device']); ?> -
                        <?php echo htmlspecialchars($parsedUA['os']); ?> -
                        <?php echo htmlspecialchars($parsedUA['browser']); ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">User Agent</div>
                    <div class="info-value" style="font-size: 12px;"><?php echo htmlspecialchars($userAgent); ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Language</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($language); ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Referrer</div>
                    <div class="info-value"><?php echo htmlspecialchars($referrer); ?>
                    </div>
                </div>

                <div class="info-item">
                    <div class="info-label">Protocol & Port</div>
                    <div class="info-value">
                        <?php echo htmlspecialchars($protocol . ':' . $port); ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="status error">
                ❌ Failed to record visit:
                <?php echo htmlspecialchars($errorMessage ?? 'Unknown error'); ?>
            </div>

            <!-- DEBUG INFO -->
            <div class="info-grid" style="margin-bottom: 20px;">
                <div class="info-item" style="border-left-color: #ffc107; background: #fff3cd;">
                    <div class="info-label">⚠️ DEBUG INFO (REMOVE IN PRODUCTION)</div>
                    <div class="info-value" style="font-family: monospace; font-size: 12px;">
                        <strong>Host:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_HOST']); ?> (Env:
                        <?php echo htmlspecialchars($debugInfo['DB_HOST_ENV'] ?? 'NULL'); ?> /
                        <?php echo htmlspecialchars($debugInfo['MYSQLHOST_ENV'] ?? 'NULL'); ?>)<br>
                        <strong>Port:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_PORT']); ?><br>
                        <strong>DB:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_NAME']); ?><br>
                        <strong>User:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_USER']); ?><br>
                        <strong>Pass:</strong> <?php echo htmlspecialchars($debugInfo['FINAL_PASS']); ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="footer">
            Timestamp:
            <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>

    <script>
        // Send additional client-side data
        if (<?php echo $success ? 'false' : 'false'; ?>) { // Only on first load
            const formData = new FormData();
            formData.append('screen_width', screen.width);
            formData.append('screen_height', screen.height);
            formData.append('timezone', Intl.DateTimeFormat().resolvedOptions().timeZone);

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
        }
    </script>
</body>

</html>