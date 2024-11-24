<!DOCTYPE html>
<html>
<head>
    <title>Answers Submitted</title>
</head>
<body>
    <h1>Multiple Answers Submitted</h1>
    @foreach($userAnswers as $userAnswer)
        <p><strong>Question:</strong> {{ $userAnswer->question->question }}</p>

        @if($userAnswer->short_answer)
            <p><strong>Answer:</strong> {{ $userAnswer->short_answer }}</p>
        @elseif($userAnswer->answer)
            <p><strong>Answer:</strong> {{ $userAnswer->answer->answer }}</p>
        @else
            <p><strong>Answer:</strong> No answer provided.</p>
        @endif

        <hr>
    @endforeach

    <p><strong>Submitted By:</strong> {{ $userAnswers[0]->user->name }}</p>
</body>
</html>
