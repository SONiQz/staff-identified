<?php
# Read INI File Data
$config = parse_ini_file(__DIR__ . '/.env');
# Allocate Credentials to Variables
$tenantId = $config['TENANT_ID'] ?? '';
$clientId = $config['CLIENT_ID'] ?? '';
$clientSecret = $config['CLIENT_SECRET'] ?? '';

# Function to get Access Token for Tenant/Web App using API Call
function getAccessToken(string $tenantId, string $clientId, string $clientSecret): string {
    $url = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";

    # Client Credential Definition
    $data = http_build_query([
        'client_id' => $clientId,
        'scope' => 'https://graph.microsoft.com/.default',
        'client_secret' => $clientSecret,
        'grant_type' => 'client_credentials'
    ]);

    # HTML Call type and call the defined Credentials
    $options = [
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded",
            'method'  => 'POST',
            'content' => $data
        ]
    ];

    # Create Stream for Context for credentials using compiled Options
    $context  = stream_context_create($options);
    # Make API Call and return Token as result
    $result = file_get_contents($url, false, $context);
    # Error handling if Token not returned
    if ($result === false) {
        throw new Exception("Failed to get access token");
    }
    # Convert token into usable format
    $json = json_decode($result, true);
    return $json['access_token'];
}

# Function to gather User Details from MS365 using Graph API
function getAllUsers(string $accessToken): array {
    # Create an Empty list for Users
    $users = [];
    # Define Endpoint URL for Users, and pass the required data variables
    $url = "https://graph.microsoft.com/v1.0/users?\$select=id,displayName,userPrincipalName,mail,accountEnabled,userType,jobTitle,department,officeLocation,mobilePhone,givenName,surname";

    # Loop to ensure that all results are returned
    do {
        # HTML Call type and call the defined Credentials
        $opts = [
            'http' => [
                'header' => "Authorization: Bearer $accessToken\r\nAccept: application/json",
                'method' => 'GET'
            ]
        ];
        # Create Stream Context for Call
        $context = stream_context_create($opts);
        # Make API Call and return users as a result
        $result = file_get_contents($url, false, $context);
        if ($result === false) break;

        # Convert results from JSON to Variable
        $json = json_decode($result, true);
        # Append results to the Users list
        $users = array_merge($users, $json['value'] ?? []);

        # Amend URL to return next batch of data
        $url = $json['@odata.nextLink'] ?? null;
    
    } 
    # Keep returning data until no next link value returned
    while ($url);
    # Output Users list
    return $users;
}

# Call Functions to generate the output
$token = getAccessToken($tenantId, $clientId, $clientSecret);
$users = getAllUsers($token);

# Filter User data
$filtered = array_filter($users, fn($user) =>
    # Only Accounts that are enabled (Disabled/Blocked Ignored)
    ($user['accountEnabled'] ?? false) &&
    # Only Users with an Email
    isset($user['mail']) &&
    # UPN includes organisational name
    str_ends_with($user['userPrincipalName'], '@contoso.com') &&
    # Ignore Room & Equipment user types
    !in_array(strtolower($user['userType'] ?? ''), ['room', 'equipment']) &&
    # Ignore if department is blank (Used to filter non-human users)
    !is_null($user['department']) &&
    # Ignore department 'Virtual' used for edge-cases.
    !preg_match('/\bVirtual\b/i', $user['department'] ?? '') &&
    # Filter other "Room Based" names from Display Name
    !preg_match('/\b(room|conf|meeting|boardroom|huddle|space)\b/i', $user['displayName'] ?? '')
);
?>
<!-- HTML Context for output -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Directory</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 2rem; }
    table { border-collapse: collapse; width: 100%; }
    th, td { padding: 8px 12px; border: 1px solid #ccc; text-align: left; }
    th { background-color: #f4f4f4; }
    tr:nth-child(even) { background-color: #fafafa; }
  </style>
</head>
<body>
  <h2>Active Users with Email – Roberts Limbrick</h2>
    <!-- Create Table -->
    <table>
        <!-- Define Headings -->
    <thead>
      <tr>
        <th>Given Name</th>
        <th>Surame</th>
        <th>Email</th>
        <th>Job Title</th>
        <th>Department</th>
        <th>Office</th>
      </tr>
    </thead>
    <tbody>
        <!-- PHP For Loop to generate User row output within HTML --> 
        <?php foreach ($filtered as $user): ?>
        <tr>
            <td><?= htmlspecialchars($user['givenName'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['surname'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['mail'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['jobTitle'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['department'] ?? '') ?></td>
            <td><?= htmlspecialchars($user['officeLocation'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
  </table>
</body>
</html>
