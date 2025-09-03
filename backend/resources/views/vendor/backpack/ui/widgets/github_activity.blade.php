@php
    use League\CommonMark\CommonMarkConverter;
    $converter = new CommonMarkConverter();
@endphp

<div class="card">
    <div class="card-header">
        <h3 class="m-0">GitHub - Development Activity</h3>
    </div>
    <div class="card-body">
        <h6>Latest Commits</h6>
        <ul>
            @foreach ($commits as $commit)
                @php
                    $commit_message = $converter->convertToHtml($commit['commit']['message']);
                @endphp
                <li class="mb-2">
                    @if (isset($commit['commit']['author']['name']) &&
                            isset($commit['commit']['message']) &&
                            isset($commit['commit']['author']['date']))
                        <a href="{{ $commit['html_url'] }}"
                            target="_blank"><strong>{{ $commit['commit']['author']['name'] }}</strong></a> -
                        <small>{{ \Carbon\Carbon::parse($commit['commit']['author']['date'])->format('d M Y') }}</small><br />
                        {!! $commit_message !!}
                    @endif
                </li>
            @endforeach
        </ul>
        <hr />

        <h6>Open Issues</h6>
        <ul>
            @foreach ($issues as $issue)
                @if (isset($issue['title']) && $issue['state'] == 'open')
                    <li>
                        <a href="{{ $issue['html_url'] }}" target="_blank"><strong>{{ $issue['title'] }}</strong></a> -
                        <small>Opened by {{ $issue['user']['login'] }} on
                            {{ \Carbon\Carbon::parse($issue['created_at'])->format('d M Y') }}</small>
                    </li>
                @endif
            @endforeach
        </ul>
        <hr />

        <h6>Closed Issues</h6>
        <ul>
            @foreach ($issues as $issue)
                @if (isset($issue['title']) && $issue['state'] != 'open')
                    <li>
                        <a href="{{ $issue['html_url'] }}" target="_blank"><strong>{{ $issue['title'] }}</strong></a> -
                        <small>Closed by {{ $issue['closed_by']['login'] }} on
                            {{ \Carbon\Carbon::parse($issue['closed_at'])->format('d M Y') }}</small>
                    </li>
                @endif
            @endforeach
        </ul>
    </div>
</div>