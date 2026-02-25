<?php
// api/thunder_api.php

class ThunderClient
{
    private string $apiKey;
    private string $baseUrl = "https://api.thunder.in.th";

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * สร้าง QR Code (v2 Endpoint)
     */
    public function generateQR(float $amount, string $reference, string $promptpayNumber): array
    {
        $payload = [
            'sourceType' => 'promptpay',
            'sourceId' => $promptpayNumber, // หมายเลขพร้อมเพย์
            'amount' => $amount,
            'reference' => $reference 
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v2/qr/generate',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $result = json_decode($response, true);

        // ตรวจสอบว่า API ตอบ 200 และมีรูป QR กลับมา
        if ($httpCode === 200 && isset($result['data']['qr_image'])) {
            return $result['data'];
        }
        
        // ถ้าล้มเหลว โยน Exception เพื่อให้ไปใช้ตัวสำรอง
        $errMsg = $result['message'] ?? 'QR Generation Failed';
        throw new Exception($errMsg);
    }

    /**
     * ตรวจสอบสลิป (จากไฟล์รูปภาพ)
     */
    public function verifyByImage(string $filePath): array
    {
        if (!file_exists($filePath)) throw new Exception("File not found.");
        $curlFile = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v1/verify',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json'
            ],
            CURLOPT_POSTFIELDS => [
                'file' => $curlFile,
                'checkDuplicate' => 'true'
            ],
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);

        if ($httpCode === 200 && isset($result['data'])) {
            return $result['data'];
        }

        $errMsg = $result['message'] ?? 'Verification Failed';
        throw new Exception($errMsg);
    }
}
?>