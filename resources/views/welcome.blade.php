@extends('layouts.master')


@section('content')
<h1>Some Content</h1>
    <p>{{ "Hello" }}</p>
    <p>{{ 2 == 3 ? "Hello" : "Does not equal" }}</p> //you cna put stuff here
@endsection
