<?php

class Token
{
    function isValidToken($token, $userId)
    {
        global $db;

        // Prepare and execute query to check if the token is valid
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
        FROM tokens t 
        JOIN users u on u.user_id = t.user_id 
        WHERE t.token = ? 
        AND (t.user_id = ? OR u.role = 'admin') 
        AND t.expiration_timestamp > NOW()"
        );
        $stmt->bind_param("si", $token, $userId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        // If count > 0, token is valid
        return $result['count'] > 0;
    }

    function isAdminToken($token)
    {
        global $db;

        // Prepare and execute query to check if the token is valid
        $stmt = $db->prepare(
            "SELECT COUNT(*) as count 
        FROM tokens t 
        JOIN users u on u.user_id = t.user_id 
        WHERE t.token = ? 
        AND u.role = 'admin' 
        AND t.expiration_timestamp > NOW()"
        );
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();

        // If count > 0, token is valid
        return $result['count'] > 0;
    }

    function getTokenFromAuthorizationHeader()
    {
        $headers = apache_request_headers();

        if (isset($headers['Authorization'])) {
            $authorizationHeader = $headers['Authorization'];
            $token = str_replace('Bearer ', '', $authorizationHeader);
            return $token;
        }

        return '';
    }

    function generateToken()
    {
        return bin2hex(random_bytes(32)); // Generate a random token
    }

    function saveToken($token, $userId)
    {
        global $db;
        date_default_timezone_set('Europe/Bratislava');
        $expiration = date('Y-m-d H:i:s', strtotime('+1 day')); // Token expiration in 1 day
        $stmt = $db->prepare("INSERT INTO tokens (token, user_id, expiration_timestamp) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $token, $userId, $expiration);
        $stmt->execute();
    }
}
