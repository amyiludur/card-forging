{{-- One grid of a Tale's campaign log: an entry per row, a scenario per column.
     The party and each character block print the same shape. A Tale with no
     scenarios yet still gets one blank column to write in. --}}
<table class="grid">
    <thead>
        <tr>
            <th></th>
            @forelse ($columns as $step)
                <th>{{ $step['number'] }}. {{ $step['scenario'] }}</th>
            @empty
                <th>&nbsp;</th>
            @endforelse
        </tr>
    </thead>
    <tbody>
        @foreach ($entries as $entry)
            <tr class="{{ $tall($entry['kind']) ? 'tall' : '' }}">
                <th>{!! $entry['name_html'] !!}</th>
                @for ($i = 0; $i < max(1, count($columns)); $i++)
                    <td>{!! $cell($entry['kind']) !!}</td>
                @endfor
            </tr>
        @endforeach
    </tbody>
</table>
