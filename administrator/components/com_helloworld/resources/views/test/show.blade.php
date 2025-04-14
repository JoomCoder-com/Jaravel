<!DOCTYPE html>
<html>
<head>
    <title>{{ $title }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 20px;
            color: #333;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .details {
            background: #e9f7fe;
            border: 1px solid #b8d8eb;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        .badge {
            display: inline-block;
            background: #3498db;
            color: white;
            padding: 3px 10px;
            border-radius: 50px;
            font-size: 14px;
            margin-left: 10px;
        }
        .back {
            display: inline-block;
            margin-top: 20px;
            padding: 8px 16px;
            background: #6c757d;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .back:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>{{ $title }} <span class="badge">ID: {{ $id }}</span></h1>
        
        <div class="details">
            <p><strong>Details:</strong> {{ $details }}</p>
        </div>
        
        <a href="{{ url('/test') }}" class="back">Back to Test List</a>
    </div>
</body>
</html> 