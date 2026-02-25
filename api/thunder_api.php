<?php
// api/thunder_api.php

// คลาสนี้เปรียบเสมือน "พนักงานสื่อสาร" ที่คอยติดต่อระหว่างระบบเรากับ Thunder API
// หน้าที่หลักคือสร้าง QR Code รับเงิน และตรวจสอบสลิปโอนเงิน
class ThunderClient
{
    // เก็บ API Key สำหรับยืนยันตัวตน
    private string $apiKey;
    // เก็บ URL หลักของ Thunder API
    private string $baseUrl = "https://api.thunder.in.th";

    // Constructor: ตอนสร้าง Object ตัวนี้ขึ้นมา ต้องใส่ "กุญแจ" (API Key) มาด้วย
    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * สร้าง QR Code (v2 Endpoint)
     * ฟังก์ชันนี้ไว้สำหรับ "สั่งทำ QR Code" ให้ลูกค้าแสกนจ่ายเงิน
     */
    public function generateQR(float $amount, string $reference, string $promptpayNumber): array
    {
        // เตรียมข้อมูล (Payload) ที่จะส่งไปบอก API ว่า:
        // - ใช้พร้อมเพย์ (sourceType: promptpay)
        // - เบอร์อะไร (sourceId)
        // - จำนวนเงินเท่าไหร่ (amount)
        // - เลขอ้างอิงคืออะไร (reference)
        $payload = [
            'sourceType' => 'promptpay',
            'sourceId' => $promptpayNumber, // หมายเลขพร้อมเพย์
            'amount' => $amount,
            'reference' => $reference 
        ];

        // เริ่มการส่งข้อมูลด้วย cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v2/qr/generate', // URL ปลายทาง
            CURLOPT_RETURNTRANSFER => true, // ให้ส่งผลลัพธ์กลับมาเป็น String
            CURLOPT_POST => true, // ใช้ Method POST
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey, // ใส่ Key เพื่อยืนยันตัวตน
                'Content-Type: application/json' // บอกว่าข้อมูลที่ส่งเป็น JSON
            ],
            CURLOPT_POSTFIELDS => json_encode($payload) // แปลง Array เป็น JSON แล้วส่งไป
        ]);

        // ประมวลผล cURL และรับผลลัพธ์กลับมา
        $response = curl_exec($ch);
        // เก็บ HTTP Status Code เพื่อเช็คว่าสำเร็จไหม (200 = สำเร็จ)
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // แปลงผลลัพธ์ JSON กลับมาเป็น Array
        $result = json_decode($response, true);

        // ตรวจสอบว่า API ตอบ 200 และมีรูป QR กลับมาไหม
        if ($httpCode === 200 && isset($result['data']['qr_image'])) {
            return $result['data']; // ส่งข้อมูลรูป QR กลับไป
        }
        
        // ถ้าล้มเหลว (ไม่ใช่ 200 หรือไม่มีรูป) ให้โยน Exception ออกไปเพื่อให้ระบบจัดการต่อ
        $errMsg = $result['message'] ?? 'QR Generation Failed';
        throw new Exception($errMsg);
    }

    /**
     * ตรวจสอบสลิป (จากไฟล์รูปภาพ)
     * ฟังก์ชันนี้ไว้สำหรับ "อ่านสลิป" ที่ลูกค้าอัปโหลดมา ว่าโอนตรงไหม ถูกต้องไหม
     */
    public function verifyByImage(string $filePath): array
    {
        // เช็คก่อนว่ามีไฟล์จริงๆ ไหม ถ้าไม่มีก็แจ้ง Error เลย
        if (!file_exists($filePath)) throw new Exception("File not found.");
        
        // เตรียมไฟล์สำหรับอัปโหลดผ่าน cURL (ใช้ Class CURLFile)
        $curlFile = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->baseUrl . '/v1/verify', // URL สำหรับตรวจสอบสลิป
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->apiKey,
                'Accept: application/json'
            ],
            // ส่งข้อมูลแบบ FormData (เหมือนฟอร์มอัปโหลดไฟล์ทั่วไป)
            CURLOPT_POSTFIELDS => [
                'file' => $curlFile, // แนบไฟล์รูปสลิปเข้าไป
                'checkDuplicate' => 'true' // สั่งให้ API เช็คด้วยว่าสลิปใบนี้เคยใช้ไปแล้วหรือยัง
            ],
            CURLOPT_TIMEOUT => 30 // ตั้ง Timeout ไว้ 30 วินาที เผื่อไฟล์ใหญ่หรือเน็ตช้า
        ]);

        // ประมวลผลและรับผลลัพธ์
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $result = json_decode($response, true);

        // ถ้า API ตอบ 200 และมี data กลับมา แสดงว่าอ่านสลิปสำเร็จ
        if ($httpCode === 200 && isset($result['data'])) {
            return $result['data']; // ส่งข้อมูลในสลิปกลับไป (เช่น ยอดเงิน, เวลา, ชื่อผู้โอน)
        }

        // ถ้าไม่สำเร็จ ให้โยน Exception พร้อมข้อความจาก API
        $errMsg = $result['message'] ?? 'Verification Failed';
        throw new Exception($errMsg);
    }
}
?>
```