@extends($layout ?? 'layouts.admin')
@section('title', 'Not in your plan')
@section('content')
<div style="max-width:520px;margin:60px auto;text-align:center;padding:0 16px">
    <h2 style="margin:0 0 8px">This is not part of your plan</h2>
    <p style="margin:0 0 20px;color:#5F5D6E">Your company's current plan does not include this feature. Ask your administrator about upgrading to the Professional plan.</p>
    <a href="{{ url()->previous() }}" class="btn">Go back</a>
</div>
@endsection