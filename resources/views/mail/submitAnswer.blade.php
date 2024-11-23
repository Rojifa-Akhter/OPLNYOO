<!DOCTYPE html>
<html>
<head>
    <title>Answer Submitted</title>
</head>
<body>
    <h1>A New Answer Has Been Submitted</h1>
    <p><strong>Question:</strong> {{ $userAnswer->question->question }}</p>
    <p><strong>Answer:</strong> {{ $userAnswer->answer->answer }}</p>
    <p><strong>Submitted By:</strong> {{ $userAnswer->user->name }}</p>
</body>
</html>
