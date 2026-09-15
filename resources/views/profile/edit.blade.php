@extends('layouts.app')
@section('title', 'Edit Profil')

@section('content')
<h3>Edit Profil</h3>

<div class="row">
    <div class="col-md-7">
        <div class="card mb-4">
            <div class="card-header">Informasi Profil</div>
            <div class="card-body">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Ubah Password</div>
            <div class="card-body">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        <div class="card border-danger">
            <div class="card-header text-danger">Hapus Akun</div>
            <div class="card-body">
                @include('profile.partials.delete-user-form')
            </div>
        </div>
    </div>
</div>
@endsection
