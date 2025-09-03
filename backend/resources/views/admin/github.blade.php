@extends(backpack_view('blank'))

{{-- @php
    function formatMarkdown($text)
    {
        // Split the text by lines
        $lines = explode("\n", $text);
        $inList = false;
        $formattedText = '';

        foreach ($lines as $line) {
            // Check if line starts with "* " (indicating a list item)
            if (preg_match('/^\* (.+)/', $line, $matches)) {
                if (!$inList) {
                    // Start the unordered list if not already in one
                    $formattedText .= '<ul>';
                    $inList = true;
                }
                // Process Markdown inside the list item text
                $itemText = $matches[1];
                $itemText = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $itemText);
                $itemText = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $itemText);
                $itemText = preg_replace('/`(.*?)`/', '<code>$1</code>', $itemText);

                // Add the processed list item
                $formattedText .= '<li>' . $itemText . '</li>';
            } else {
                // If we're currently in a list, close it when encountering a non-list line
            if ($inList) {
                $formattedText .= '</ul>';
                $inList = false;
            }
            // Process other Markdown formatting (bold, italic, code) for non-list lines
            $line = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $line);
            $line = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $line);
            $line = preg_replace('/`(.*?)`/', '<code>$1</code>', $line);

            // Add the processed line as is (don't escape HTML)
                $formattedText .= $line;
            }
        }

        // Close any open list tags at the end of the text
        if ($inList) {
            $formattedText .= '</ul>';
        }

        return $formattedText;
    }
@endphp --}}

@php
    use League\CommonMark\CommonMarkConverter;
    $converter = new CommonMarkConverter();
    if ($wiki != '') {
        $html = $converter->convertToHtml($wiki);
    } else {
        $html = 'Clone effettuato! La wiki è pronta :)';
    }

@endphp

@section('content')
    <div class="container">
        <h1>GitHub</h1>
    </div>
    <div class="container">
        @if ($wiki = '')
            <span>{ $html }</span>
        @else
            <span>{!! $html !!}</span>
        @endif
    </div>
@endsection
