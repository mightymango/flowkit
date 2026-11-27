@extends(config('flowkit.layout'))

@section('title', $flow->name)

@section('content')
    <livewire:flowkit.flow-editor :flow="$flow" />
@endsection
