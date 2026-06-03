<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8" />
    <title>No Records Found</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; margin: 0; padding: 0; }
        .wrap { width: 100%; height: 100%; min-height: 600px; display: flex; align-items: center; justify-content: center; }
        .card { width: 85%; max-width: 720px; border: 2px solid #1a365d; border-radius: 10px; padding: 28px 22px; text-align: center; }
        .title { color: #1a365d; font-weight: 800; font-size: 22px; margin-bottom: 10px; }
        .msg { color: #475569; font-size: 14px; line-height: 1.4; }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="card">
            <div class="title">No Records Found</div>
            <div class="msg">{{ $message ?? '-' }}</div>
        </div>
    </div>
</body>
</html>

