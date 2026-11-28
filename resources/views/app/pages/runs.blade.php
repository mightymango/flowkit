@extends(config('flowkit.layout'))

@section('title', __('flowkit::runs.title', ['flow' => $flow->name]))

@section('content')
    <livewire:flowkit.run-history :flow="$flow" />
@endsection
