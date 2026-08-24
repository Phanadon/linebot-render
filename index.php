<?php

header("Content-Type: text/plain; charset=UTF-8");

/* =========================================================
   LINE ACCESS TOKEN
========================================================= */

$access_token = "8rCwm7/Y8U6ccPjUV9XbnEWe/bPZpuhxE7piiR9lE0LlPrVA6+4QPQxodB3GDEQv+0KhsGrejWjG4RuwfoM82D+LqKj5okz1bcxsyNr3QxK3rpsbLmY6gyCbm605kmx6XUJydcwCtbMY0VlSaRCJuQdB04t89/1O/w1cDnyilFU=";


/* =========================================================
   DATABASE
========================================================= */

$db_host = "sql108.infinityfree.com";
$db_user = "if0_42713086";
$db_pass = "mB3Y9lLd7R";
$db_name = "if0_42713086_bot_db";


/* =========================================================
   DIALOGFLOW ES
========================================================= */

$dialogflow_project_id = "cit0008bot-lbte";

$dialogflow_credentials =
    "/etc/secrets/dialogflow-key.json";


/* =========================================================
   LOG FUNCTION
========================================================= */

function writeLog($message)
{
    file_put_contents(
        __DIR__ . "/log.txt",
        date("Y-m-d H:i:s") .
        " | " .
        $message .
        PHP_EOL,
        FILE_APPEND
    );
}


/* =========================================================
   BASE64 URL ENCODE
========================================================= */

function base64UrlEncode($data)
{
    return rtrim(
        strtr(
            base64_encode($data),
            "+/",
            "-_"
        ),
        "="
    );
}


/* =========================================================
   DIALOGFLOW ES
========================================================= */

function callDialogflow($text, $sessionId)
{
    global $dialogflow_project_id;
    global $dialogflow_credentials;

    writeLog(
        "Dialogflow INPUT: " .
        $text
    );

    /* =====================================================
       CHECK CREDENTIAL FILE
    ===================================================== */

    if (!file_exists($dialogflow_credentials)) {

        writeLog(
            "ไม่พบ Dialogflow JSON: " .
            $dialogflow_credentials
        );

        return array(
            "success" => false
        );
    }


    /* =====================================================
       READ CREDENTIAL
    ===================================================== */

    $credentialsJson =
        file_get_contents(
            $dialogflow_credentials
        );

    $credentials =
        json_decode(
            $credentialsJson,
            true
        );


    if (!$credentials) {

        writeLog(
            "อ่าน Dialogflow JSON ไม่ได้"
        );

        return array(
            "success" => false
        );
    }


    /* =====================================================
       CHECK CREDENTIAL
    ===================================================== */

    if (
        !isset($credentials["client_email"]) ||
        !isset($credentials["private_key"])
    ) {

        writeLog(
            "Credential ไม่มี client_email หรือ private_key"
        );

        return array(
            "success" => false
        );
    }


    /* =====================================================
       CREATE JWT HEADER
    ===================================================== */

    $header = array(
        "alg" => "RS256",
        "typ" => "JWT"
    );


    /* =====================================================
       CREATE JWT CLAIM
    ===================================================== */

    $now = time();

    $claim = array(

        "iss" =>
            $credentials["client_email"],

        "scope" =>
            "https://www.googleapis.com/auth/cloud-platform",

        "aud" =>
            "https://oauth2.googleapis.com/token",

        "iat" =>
            $now,

        "exp" =>
            $now + 3600
    );


    /* =====================================================
       ENCODE JWT
    ===================================================== */

    $base64Header =
        base64UrlEncode(
            json_encode(
                $header
            )
        );

    $base64Claim =
        base64UrlEncode(
            json_encode(
                $claim
            )
        );


    $unsignedJwt =
        $base64Header .
        "." .
        $base64Claim;


    /* =====================================================
       PRIVATE KEY
    ===================================================== */

    $privateKey =
        openssl_pkey_get_private(
            $credentials["private_key"]
        );


    if (!$privateKey) {

        writeLog(
            "ไม่สามารถเปิด private key ได้"
        );

        return array(
            "success" => false
        );
    }


    /* =====================================================
       SIGN JWT
    ===================================================== */

    $signature = "";

    $signResult =
        openssl_sign(
            $unsignedJwt,
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );


    if (!$signResult) {

        writeLog(
            "สร้าง JWT Signature ไม่สำเร็จ"
        );

        return array(
            "success" => false
        );
    }


    /* =====================================================
       CREATE JWT
    ===================================================== */

    $jwt =
        $unsignedJwt .
        "." .
        base64UrlEncode(
            $signature
        );


    /* =====================================================
       GET GOOGLE ACCESS TOKEN
    ===================================================== */

    $tokenCh =
        curl_init(
            "https://oauth2.googleapis.com/token"
        );


    $tokenPost =
        http_build_query(
            array(

                "grant_type" =>
                    "urn:ietf:params:oauth:grant-type:jwt-bearer",

                "assertion" =>
                    $jwt
            )
        );


    curl_setopt(
        $tokenCh,
        CURLOPT_POST,
        true
    );

    curl_setopt(
        $tokenCh,
        CURLOPT_RETURNTRANSFER,
        true
    );

    curl_setopt(
        $tokenCh,
        CURLOPT_POSTFIELDS,
        $tokenPost
    );

    curl_setopt(
        $tokenCh,
        CURLOPT_HTTPHEADER,
        array(
            "Content-Type: application/x-www-form-urlencoded"
        )
    );


    $tokenResponse =
        curl_exec(
            $tokenCh
        );


    $tokenHttpCode =
        curl_getinfo(
            $tokenCh,
            CURLINFO_HTTP_CODE
        );


    $tokenError =
        curl_error(
            $tokenCh
        );


    curl_close(
        $tokenCh
    );


    writeLog(
        "Google Token HTTP: " .
        $tokenHttpCode
    );


    if ($tokenError != "") {

        writeLog(
            "Google Token CURL ERROR: " .
            $tokenError
        );

        return array(
            "success" => false
        );
    }


    /* =====================================================
       DECODE TOKEN
    ===================================================== */

    $tokenData =
        json_decode(
            $tokenResponse,
            true
        );


    if (
        !isset(
            $tokenData["access_token"]
        )
    ) {

        writeLog(
            "ไม่สามารถขอ Google Access Token"
        );

        writeLog(
            "TOKEN RESPONSE: " .
            $tokenResponse
        );

        return array(
            "success" => false
        );
    }


    $googleAccessToken =
        $tokenData["access_token"];


    writeLog(
        "Google Access Token สำเร็จ"
    );


    /* =====================================================
       DIALOGFLOW DETECT INTENT URL
    ===================================================== */

    $url =
        "https://dialogflow.googleapis.com/v2/projects/" .
        $dialogflow_project_id .
        "/agent/sessions/" .
        urlencode($sessionId) .
        ":detectIntent";


    /* =====================================================
       REQUEST BODY
    ===================================================== */

    $body = array(

        "queryInput" => array(

            "text" => array(

                "text" =>
                    $text,

                "languageCode" =>
                    "th"
            )
        )
    );


    $jsonBody =
        json_encode(
            $body,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


    writeLog(
        "Dialogflow REQUEST: " .
        $jsonBody
    );


    /* =====================================================
       CALL DIALOGFLOW
    ===================================================== */

    $ch =
        curl_init(
            $url
        );


    curl_setopt(
        $ch,
        CURLOPT_POST,
        true
    );

    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );

    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        $jsonBody
    );

    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(

            "Content-Type: application/json",

            "Authorization: Bearer " .
            $googleAccessToken
        )
    );


    $response =
        curl_exec(
            $ch
        );


    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    $curlError =
        curl_error(
            $ch
        );


    curl_close(
        $ch
    );


    /* =====================================================
       LOG
    ===================================================== */

    writeLog(
        "Dialogflow HTTP CODE: " .
        $httpCode
    );


    if ($curlError != "") {

        writeLog(
            "Dialogflow CURL ERROR: " .
            $curlError
        );

        return array(
            "success" => false
        );
    }


    writeLog(
        "Dialogflow RAW: " .
        $response
    );


    /* =====================================================
       DECODE
    ===================================================== */

    $data =
        json_decode(
            $response,
            true
        );


    if (
        $httpCode != 200 ||
        !isset(
            $data["queryResult"]
        )
    ) {

        writeLog(
            "Dialogflow API ERROR"
        );

        return array(

            "success" =>
                false,

            "raw" =>
                $data
        );
    }


    $queryResult =
        $data["queryResult"];


    /* =====================================================
       GET INTENT
    ===================================================== */

    $intentName = "";

    if (
        isset(
            $queryResult["intent"]["displayName"]
        )
    ) {

        $intentName =
            $queryResult["intent"]["displayName"];
    }


    /* =====================================================
       GET RESPONSE
    ===================================================== */

    $responseText = "";


    if (
        isset(
            $queryResult["fulfillmentText"]
        ) &&
        $queryResult["fulfillmentText"] != ""
    ) {

        $responseText =
            $queryResult["fulfillmentText"];

    } elseif (
        isset(
            $queryResult["fulfillmentMessages"][0]["text"]["text"][0]
        )
    ) {

        $responseText =
            $queryResult["fulfillmentMessages"][0]["text"]["text"][0];
    }


    writeLog(
        "Dialogflow Intent: " .
        $intentName
    );

    writeLog(
        "Dialogflow Response: " .
        $responseText
    );


    /* =====================================================
       RETURN
    ===================================================== */

    return array(

        "success" =>
            true,

        "intent" =>
            $intentName,

        "response" =>
            $responseText,

        "raw" =>
            $data
    );
}


/* =========================================================
   CREATE PRODUCT FLEX
========================================================= */

function getProductMessages($conn)
{
    writeLog(
        "กำลังดึงสินค้าจากฐานข้อมูล"
    );


    $sql = "
        SELECT
            id,
            name,
            price,
            image,
            link_url
        FROM products
        ORDER BY id ASC
    ";


    $result =
        $conn->query(
            $sql
        );


    if (!$result) {

        writeLog(
            "SQL ERROR: " .
            $conn->error
        );

        return array(

            array(

                "type" =>
                    "text",

                "text" =>
                    "เกิดข้อผิดพลาดในการดึงข้อมูลสินค้าครับ"
            )
        );
    }


    writeLog(
        "พบสินค้า: " .
        $result->num_rows .
        " รายการ"
    );


    $bubbles = array();


    while (
        $row =
        $result->fetch_assoc()
    ) {

        $name =
            trim(
                $row["name"]
            );


        $price =
            number_format(
                $row["price"],
                2
            );


        $image =
            trim(
                $row["image"]
            );


        $link =
            trim(
                $row["link_url"]
            );


        /* =================================================
           IMAGE
        ================================================= */

        if (
            $image == "" ||
            !filter_var(
                $image,
                FILTER_VALIDATE_URL
            )
        ) {

            $image =
                "https://via.placeholder.com/1024x1024.png?text=No+Image";
        }


        /* =================================================
           LINK
        ================================================= */

        if (
            $link == "" ||
            !filter_var(
                $link,
                FILTER_VALIDATE_URL
            )
        ) {

            $link =
                "https://www.google.com/";
        }


        /* =================================================
           FLEX BUBBLE
        ================================================= */

        $bubble = array(

            "type" =>
                "bubble",

            "hero" =>
                array(

                    "type" =>
                        "image",

                    "url" =>
                        $image,

                    "size" =>
                        "full",

                    "aspectRatio" =>
                        "1:1",

                    "aspectMode" =>
                        "cover"
                ),

            "body" =>
                array(

                    "type" =>
                        "box",

                    "layout" =>
                        "vertical",

                    "spacing" =>
                        "md",

                    "contents" =>
                        array(

                            array(

                                "type" =>
                                    "text",

                                "text" =>
                                    $name,

                                "weight" =>
                                    "bold",

                                "size" =>
                                    "md",

                                "wrap" =>
                                    true
                            ),

                            array(

                                "type" =>
                                    "text",

                                "text" =>
                                    "ราคา " .
                                    $price .
                                    " บาท",

                                "size" =>
                                    "lg",

                                "weight" =>
                                    "bold",

                                "color" =>
                                    "#FF0000",

                                "margin" =>
                                    "md"
                            )
                        )
                ),

            "footer" =>
                array(

                    "type" =>
                        "box",

                    "layout" =>
                        "vertical",

                    "spacing" =>
                        "sm",

                    "contents" =>
                        array(

                            array(

                                "type" =>
                                    "button",

                                "style" =>
                                    "primary",

                                "action" =>
                                    array(

                                        "type" =>
                                            "uri",

                                        "label" =>
                                            "ดูสินค้า",

                                        "uri" =>
                                            $link
                                    )
                            )
                        )
                )
        );


        $bubbles[] =
            $bubble;
    }


    /* =====================================================
       NO PRODUCT
    ===================================================== */

    if (
        count($bubbles) == 0
    ) {

        return array(

            array(

                "type" =>
                    "text",

                "text" =>
                    "ไม่พบข้อมูลสินค้าในระบบครับ"
            )
        );
    }


    /* =====================================================
       FLEX CAROUSEL
    ===================================================== */

    $flexMessage =
        array(

            "type" =>
                "flex",

            "altText" =>
                "🛒 รายการสินค้า",

            "contents" =>
                array(

                    "type" =>
                        "carousel",

                    "contents" =>
                        $bubbles
                )
        );


    return array(
        $flexMessage
    );
}


/* =========================================================
   START
========================================================= */

writeLog(
    "LINE PHP ถูกเรียก"
);


/* =========================================================
   DATABASE CONNECT
========================================================= */

$conn =
    new mysqli(
        $db_host,
        $db_user,
        $db_pass,
        $db_name
    );


if (
    $conn->connect_error
) {

    writeLog(
        "DB ERROR: " .
        $conn->connect_error
    );

    http_response_code(
        500
    );

    echo "DB ERROR";

    exit;
}


$conn->set_charset(
    "utf8mb4"
);


/* =========================================================
   GET LINE WEBHOOK
========================================================= */

$content =
    file_get_contents(
        "php://input"
    );


writeLog(
    "RAW: " .
    $content
);


if (
    $content == ""
) {

    writeLog(
        "RAW ว่าง"
    );

    echo "OK";

    exit;
}


/* =========================================================
   JSON DECODE
========================================================= */

$events =
    json_decode(
        $content,
        true
    );


if (!$events) {

    writeLog(
        "JSON ERROR: " .
        json_last_error_msg()
    );

    echo "OK";

    exit;
}


if (
    !isset(
        $events["events"]
    )
) {

    writeLog(
        "ไม่พบ events"
    );

    echo "OK";

    exit;
}


/* =========================================================
   LOOP EVENTS
========================================================= */

foreach (
    $events["events"]
    as $event
) {

    /* =====================================================
       EVENT TYPE
    ===================================================== */

    if (
        !isset($event["type"]) ||
        $event["type"] != "message"
    ) {

        continue;
    }


    /* =====================================================
       MESSAGE TYPE
    ===================================================== */

    if (
        !isset($event["message"]["type"]) ||
        $event["message"]["type"] != "text"
    ) {

        continue;
    }


    /* =====================================================
       REPLY TOKEN
    ===================================================== */

    if (
        !isset($event["replyToken"])
    ) {

        continue;
    }


    /* =====================================================
       USER MESSAGE
    ===================================================== */

    $replyToken =
        $event["replyToken"];


    $userMessage =
        trim(
            $event["message"]["text"]
        );


    writeLog(
        "USER MESSAGE: " .
        $userMessage
    );


    /* =====================================================
       LINE USER ID
       ใช้เป็น Dialogflow Session
    ===================================================== */

    $sessionId = "default";


    if (
        isset(
            $event["source"]["userId"]
        )
    ) {

        $sessionId =
            $event["source"]["userId"];
    }


    writeLog(
        "Dialogflow Session ID: " .
        $sessionId
    );


    /* =====================================================
       ส่งทุกข้อความเข้า Dialogflow
    ===================================================== */

    $dialogflowResult =
        callDialogflow(
            $userMessage,
            $sessionId
        );


    /* =====================================================
       DIALOGFLOW ERROR
    ===================================================== */

    if (
        !$dialogflowResult ||
        !isset(
            $dialogflowResult["success"]
        ) ||
        $dialogflowResult["success"] !== true
    ) {

        $messages =
            array(

                array(

                    "type" =>
                        "text",

                    "text" =>
                        "ขออภัยครับ ระบบไม่สามารถประมวลผลข้อความนี้ได้"
                )
            );

    } else {


        /* =================================================
           GET INTENT
        ================================================= */

        $intent =
            isset(
                $dialogflowResult["intent"]
            )
                ? $dialogflowResult["intent"]
                : "";


        $dialogflowResponse =
            isset(
                $dialogflowResult["response"]
            )
                ? $dialogflowResult["response"]
                : "";


        writeLog(
            "FINAL INTENT: " .
            $intent
        );


        /* =================================================
           ASK PRODUCT
           Dialogflow Intent = ask_product
        ================================================= */

        if (
            $intent == "ask_product"
        ) {

            writeLog(
                "ตรวจพบ Intent ask_product"
            );


            $messages =
                getProductMessages(
                    $conn
                );


        } else {


            /* =================================================
               INTENT อื่น ๆ
               ใช้คำตอบจาก Dialogflow
            ================================================= */

            if (
                $dialogflowResponse == ""
            ) {

                $dialogflowResponse =
                    "ขออภัยครับ ไม่พบคำตอบสำหรับข้อความนี้";
            }


            $messages =
                array(

                    array(

                        "type" =>
                            "text",

                        "text" =>
                            $dialogflowResponse
                    )
                );
        }
    }


    /* =====================================================
       CREATE LINE REPLY
    ===================================================== */

    $data =
        array(

            "replyToken" =>
                $replyToken,

            "messages" =>
                $messages
        );


    $post =
        json_encode(
            $data,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        );


    writeLog(
        "SEND LINE: " .
        $post
    );


    /* =====================================================
       LINE REPLY API
    ===================================================== */

    $ch =
        curl_init(
            "https://api.line.me/v2/bot/message/reply"
        );


    curl_setopt(
        $ch,
        CURLOPT_POST,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_RETURNTRANSFER,
        true
    );


    curl_setopt(
        $ch,
        CURLOPT_POSTFIELDS,
        $post
    );


    curl_setopt(
        $ch,
        CURLOPT_HTTPHEADER,
        array(

            "Content-Type: application/json",

            "Authorization: Bearer " .
            $access_token
        )
    );


    $response =
        curl_exec(
            $ch
        );


    $httpCode =
        curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );


    $curlError =
        curl_error(
            $ch
        );


    curl_close(
        $ch
    );


    /* =====================================================
       LOG LINE RESPONSE
    ===================================================== */

    writeLog(
        "LINE HTTP CODE: " .
        $httpCode
    );


    writeLog(
        "CURL ERROR: " .
        $curlError
    );


    writeLog(
        "LINE RESPONSE: " .
        $response
    );


    if (
        $httpCode == 200
    ) {

        writeLog(
            "ส่งข้อความกลับ LINE สำเร็จ"
        );

    } else {

        writeLog(
            "ส่งข้อความกลับ LINE ไม่สำเร็จ"
        );
    }
}


/* =========================================================
   CLOSE DATABASE
========================================================= */

$conn->close();


/* =========================================================
   RESPONSE TO LINE
========================================================= */

http_response_code(
    200
);

echo "OK";

?>