@extends(config('flowkit.layout'))

@section('title', __('flowkit::editor.flows'))

@section('content')
    <livewire:flowkit.flow-list />
@endsection
