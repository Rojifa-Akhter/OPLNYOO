<!DOCTYPE html>
<html>
<head>
    <title>Answers Submitted</title>
</head>
<body>
    <h1>Answers Submitted</h1>

    @foreach($userAnswers as $userAnswer)
        <p><strong>Question:</strong> {{ $userAnswer->question->question }}</p>
        @if($userAnswer->short_answer)
            <p><strong>Answer:</strong> {{ $userAnswer->short_answer }}</p>
        @elseif($userAnswer->options)
            <p><strong>Answer:</strong> {{ implode(', ', json_decode($userAnswer->options, true)) }}</p>
        @else
            <p><strong>Answer:</strong> No answer provided.</p>
        @endif

    @endforeach
    <hr>
    <p><strong>Submitted By:</strong> {{ $user->name }}</p>
    <p><strong>Submitted Email:</strong> {{ $user->email }}</p>
</body>
</html>
