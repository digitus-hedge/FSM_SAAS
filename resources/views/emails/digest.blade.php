<div style="font-family:Arial,sans-serif;font-size:14px;color:#1B1A24">
  <h2 style="font-size:18px">{{ $items->count() + $more }} new messages from {{ $company }}</h2>
  @foreach ($items->groupBy('event_key') as $event => $group)
    <h3 style="font-size:14px;margin:14px 0 4px">{{ $event }}@if ($group->count() > 1) ({{ $group->count() }})@endif</h3>
    @foreach ($group as $i)
      <p style="margin:2px 0">{{ $i->title }}@if ($i->body) – {{ $i->body }}@endif</p>
    @endforeach
  @endforeach
  @if ($more > 0)<p>and {{ $more }} more</p>@endif
</div>
