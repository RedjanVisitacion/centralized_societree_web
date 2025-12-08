<?php
/**
 * SMS Chef - Emergency SMS Configuration
 * Update these values with your SMSChef API credentials
 */

// SMSChef API Configuration
define('SMSCHEF_API_URL', 'https://www.cloud.smschef.com/api/send/sms');
define('SMSCHEF_SECRET', '824473088830bc5c370d732ce0c8c5aa9839588e'); // Your API Secret from SMSChef dashboard
define('SMSCHEF_DEVICE', 'cb723b0014acd1b3'); // Your device ID from SMSChef dashboard
define('SMSCHEF_DEVICE_NAME', 'RMX3261'); // Device name: itel P661N (Android 13)
define('SMSCHEF_SIM', '2'); // SIM slot (1 or 2 for dual SIM)
define('SMSCHEF_PRIORITY', '1'); // Priority level (1 = normal)

// Emergency SMS Settings
define('EMERGENCY_TIMEOUT', 30); // Request timeout in seconds
define('ENABLE_LOCATION_CACHE', false); // Cache location for faster subsequent requests

