@extends('layouts.admin')
@section('title', 'Product Reviews')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Moderation</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Product Reviews</li>
                        </ol>
                    </nav>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row row-cols-1 row-cols-md-3 mb-3">
                @foreach(['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'] as $key => $colour)
                    <div class="col">
                        <a href="{{ route('admin.reviews', ['status' => $key]) }}" class="text-decoration-none">
                            <div class="card radius-10 border-start border-0 border-4 border-{{ $colour }}">
                                <div class="card-body">
                                    <p class="mb-1 text-secondary text-capitalize">{{ $key }}</p>
                                    <h4 class="my-1 text-{{ $colour }}">{{ $counts[$key] }}</h4>
                                </div>
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <div class="d-flex align-items-center mb-2">
                <h6 class="mb-0 text-uppercase">Product Reviews</h6>
                @if($status)
                    <a href="{{ route('admin.reviews') }}" class="btn btn-sm btn-outline-secondary ms-3">Clear filter ({{ $status }})</a>
                @endif
                @include('admin.partials.export_buttons', ['list' => 'reviews', 'class' => 'ms-auto'])
            </div>
            <hr />

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Product</th>
                                    <th>Customer</th>
                                    <th>Rating</th>
                                    <th>Review</th>
                                    <th>Verified</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($reviews as $index => $review)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $review->product->name ?? 'Deleted product' }}</td>
                                        <td>{{ $review->user ? trim($review->user->first_name . ' ' . $review->user->last_name) : 'Deleted user' }}</td>
                                        <td>
                                            <span class="badge bg-primary">{{ $review->rating }} / 5</span>
                                        </td>
                                        <td>
                                            @if($review->title)<strong class="d-block">{{ $review->title }}</strong>@endif
                                            {{ Str::limit($review->review_text, 90) ?: '—' }}
                                        </td>
                                        <td>
                                            @if($review->verified)
                                                <span class="badge bg-success">Verified purchase</span>
                                            @else
                                                <span class="badge bg-secondary">Unverified</span>
                                            @endif
                                        </td>
                                        <td>
                                            @php
                                                $badge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$review->status] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $badge }} text-capitalize">{{ $review->status }}</span>
                                        </td>
                                        <td class="text-nowrap">
                                            @if($review->status !== 'approved')
                                                <form action="{{ route('admin.reviews.status', $review->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="status" value="approved">
                                                    <button type="submit" class="btn btn-sm btn-outline-success"><i class="bx bx-check"></i></button>
                                                </form>
                                            @endif
                                            @if($review->status !== 'rejected')
                                                <form action="{{ route('admin.reviews.status', $review->id) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <input type="hidden" name="status" value="rejected">
                                                    <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bx bx-x"></i></button>
                                                </form>
                                            @endif
                                            <form action="{{ route('admin.reviews.destroy', $review->id) }}" method="POST" class="d-inline"
                                                  onsubmit="return confirm('Delete this review permanently?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bx bx-trash"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center">No reviews found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
