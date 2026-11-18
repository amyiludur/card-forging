{{--
    A keyword's reminder text after the card text that uses it. Every one
    starts hidden: the script at the foot of the print sheet shows each in
    turn and keeps it only if the card still has room, so a card short of
    space prints exactly as it did before reminders existed. Not on the pocket
    page, which has the keyword glossary instead (showArt is its flag).
--}}
@if (($options->reminders ?? false) && ($options->showArt ?? true))
    @foreach ($reminders ?? [] as $reminder)
        <div class="reminder" hidden>({!! $reminder['html'] !!})</div>
    @endforeach
@endif
