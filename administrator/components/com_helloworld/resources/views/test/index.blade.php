<style>

    h1 {
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 10px;
    }
    .message {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        padding: 15px;
        border-radius: 4px;
        margin: 20px 0;
    }
    .links {
        margin-top: 30px;
    }
    .links a {
        display: inline-block;
        padding: 8px 16px;
        background: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 4px;
        margin-right: 10px;
    }
    .links a:hover {
        background: #2980b9;
    }
</style>
<h1>{{ $title }}</h1>

<div class="message">
    {{ $message }}
</div>

<div class="links">
    <a href="{{ url('/test/1') }}">View Test Item #1</a>
    <a href="{{ url('/test/2') }}">View Test Item #2</a>
    <a href="{{ url('/') }}">Back to Home</a>
</div>