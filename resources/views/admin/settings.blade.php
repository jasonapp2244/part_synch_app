@extends('layouts.admin')
@section('title', 'Settings')

@section('content')
    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Settings</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Settings</li>
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

            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <form action="{{ route('admin.settings.update') }}" method="POST">
                @csrf

                <!-- General Settings -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bx bx-cog"></i> General Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Site Name</label>
                                <input type="text" class="form-control" name="site_name" value="{{ $currentSettings['site_name'] }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Admin Email</label>
                                <input type="email" class="form-control" name="site_email" value="{{ $currentSettings['site_email'] }}">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="site_phone" value="{{ $currentSettings['site_phone'] }}">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Currency Code</label>
                                <input type="text" class="form-control" name="currency" value="{{ $currentSettings['currency'] }}" placeholder="USD">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Currency Symbol</label>
                                <input type="text" class="form-control" name="currency_symbol" value="{{ $currentSettings['currency_symbol'] }}" placeholder="$">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Commission Settings -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bx bx-money"></i> Commission Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Commission Rate</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="vendor_commission_rate" value="{{ $currentSettings['vendor_commission_rate'] }}" step="0.01" min="0">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Commission Type</label>
                                <select class="form-select" name="commission_type">
                                    <option value="percentage" {{ $currentSettings['commission_type'] === 'percentage' ? 'selected' : '' }}>Percentage</option>
                                    <option value="fixed" {{ $currentSettings['commission_type'] === 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Settings -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bx bx-cart"></i> Order Settings</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Minimum Order Amount</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currentSettings['currency_symbol'] }}</span>
                                    <input type="number" class="form-control" name="min_order_amount" value="{{ $currentSettings['min_order_amount'] }}" step="0.01" min="0">
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Default Tax Rate</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="tax_rate" value="{{ $currentSettings['tax_rate'] }}" step="0.01" min="0">
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Default Shipping Fee</label>
                                <div class="input-group">
                                    <span class="input-group-text">{{ $currentSettings['currency_symbol'] }}</span>
                                    <input type="number" class="form-control" name="shipping_default" value="{{ $currentSettings['shipping_default'] }}" step="0.01" min="0">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mobile App Settings -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bx bx-mobile-alt"></i> Mobile App</h6>
                        <small class="text-secondary">Served to the app by /api/app-config on launch.</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Minimum Android Version</label>
                                <input type="text" class="form-control" name="min_app_version_android" value="{{ $currentSettings['min_app_version_android'] }}" placeholder="1.0.0">
                                <small class="text-secondary">Older builds are forced to update.</small>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Latest Android Version</label>
                                <input type="text" class="form-control" name="latest_app_version_android" value="{{ $currentSettings['latest_app_version_android'] }}" placeholder="1.0.0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Minimum iOS Version</label>
                                <input type="text" class="form-control" name="min_app_version_ios" value="{{ $currentSettings['min_app_version_ios'] }}" placeholder="1.0.0">
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label">Latest iOS Version</label>
                                <input type="text" class="form-control" name="latest_app_version_ios" value="{{ $currentSettings['latest_app_version_ios'] }}" placeholder="1.0.0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Play Store URL</label>
                                <input type="url" class="form-control" name="play_store_url" value="{{ $currentSettings['play_store_url'] }}" placeholder="https://play.google.com/store/apps/details?id=...">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">App Store URL</label>
                                <input type="url" class="form-control" name="app_store_url" value="{{ $currentSettings['app_store_url'] }}" placeholder="https://apps.apple.com/app/id...">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Force Update Message</label>
                                <input type="text" class="form-control" name="force_update_message" value="{{ $currentSettings['force_update_message'] }}">
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" name="maintenance_mode" value="1" id="maintenanceMode" {{ $currentSettings['maintenance_mode'] ? 'checked' : '' }}>
                                    <label class="form-check-label" for="maintenanceMode">Maintenance Mode</label>
                                </div>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label class="form-label">Maintenance Message</label>
                                <input type="text" class="form-control" name="maintenance_message" value="{{ $currentSettings['maintenance_message'] }}">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Boost Payments -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bx bx-credit-card"></i> Boost Payments</h6>
                        <small class="text-secondary">Boosts are paid by card in the app (Stripe). Products and services are paid outside the app.</small>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="boost_card_payment" value="1" id="boostCardPayment" {{ $currentSettings['boost_card_payment'] ? 'checked' : '' }}>
                            <label class="form-check-label" for="boostCardPayment">In-app card payment for boosts</label>
                        </div>
                        <small class="text-secondary d-block mt-1">
                            Off: vendors send a boost request instead, you take payment outside the app and approve it under Boost Requests.
                            @unless(app(\App\Services\StripeService::class)->isConfigured())
                                <span class="text-danger">Stripe keys are not set on this server, so card payment is unavailable.</span>
                            @endunless
                        </small>
                    </div>
                </div>

                <!-- Legal & Support -->
                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bx bx-shield"></i> Legal &amp; Support</h6>
                        <small class="text-secondary">
                            Required for App Store and Play Store submission. A reachable privacy policy is mandatory on both stores.
                        </small>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">The privacy policy, terms of use, support page, account-deletion page and support contact details now have their own screen with an easy editor.</p>
                        <a href="{{ route('admin.legal') }}" class="btn btn-outline-primary btn-sm">
                            <i class="bx bx-shield"></i> Open Legal &amp; Support
                        </a>
                    </div>
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bx bx-save"></i> Save Settings
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
