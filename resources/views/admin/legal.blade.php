@extends('layouts.admin')

@section('title', 'Legal & Support')

@section('content')
    <link href="{{ asset('admin/plugins/quill/quill.snow.css') }}" rel="stylesheet">
    <style>
        .legal-editor { min-height: 420px; font-size: 15px; background: #fff; }
        .legal-editor .ql-editor { min-height: 420px; }
        .ql-toolbar.ql-snow { background: #f8f9fa; border-radius: .375rem .375rem 0 0; }
        .ql-container.ql-snow { border-radius: 0 0 .375rem .375rem; }
    </style>

    <div class="page-wrapper">
        <div class="page-content">
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Legal &amp; Support</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}"><i class="bx bx-home-alt"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Legal &amp; Support</li>
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

            <div class="alert alert-info">
                <i class="bx bx-info-circle"></i>
                These pages are required by the App Store and Google Play. The app shows them straight away, with no new
                build, and each one also has a public web address for the store listings. The starting text is a
                suggested template: replace anything in <strong>[SQUARE BRACKETS]</strong> and have it reviewed before
                publishing.
            </div>

            <form id="legal-form" action="{{ route('admin.legal.update') }}" method="POST">
                @csrf

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="bx bx-support"></i> Support contact</h6>
                        <small class="text-secondary">Shown on the app's Help &amp; Support screen and the public support page. Contact-form messages are emailed to the support email.</small>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="support_email">Support email</label>
                                <input type="email" class="form-control" id="support_email" name="support_email" value="{{ old('support_email', $contact['support_email']) }}" placeholder="support@yourcompany.com">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="support_phone">Support phone</label>
                                <input type="text" class="form-control" id="support_phone" name="support_phone" value="{{ old('support_phone', $contact['support_phone']) }}" placeholder="+1 555 000 0000">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <ul class="nav nav-tabs" role="tablist">
                            @foreach($documents as $key => $doc)
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#tab-{{ $key }}" type="button" role="tab">
                                        {{ $doc['title'] }}
                                        @if($doc['html'] === '')
                                            <span class="badge bg-secondary ms-1">Empty</span>
                                        @elseif($doc['has_placeholders'])
                                            <span class="badge bg-warning text-dark ms-1">Needs details</span>
                                        @endif
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="tab-content pt-3">
                            @foreach($documents as $key => $doc)
                                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="tab-{{ $key }}" role="tabpanel">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                        <div class="text-secondary small">
                                            Public page:
                                            <a href="{{ route($doc['route']) }}" target="_blank" rel="noopener">{{ route($doc['route']) }}</a>
                                            <br>
                                            Last updated: {{ $doc['updated_at'] ? $doc['updated_at']->format('d M Y, H:i') : 'never' }}
                                        </div>
                                        <a href="{{ route($doc['route']) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">
                                            <i class="bx bx-link-external"></i> View live page
                                        </a>
                                    </div>

                                    @if($doc['has_placeholders'])
                                        <div class="alert alert-warning py-2 small">
                                            This text still has placeholders in [SQUARE BRACKETS]. Replace them with your real details before publishing your store listing.
                                        </div>
                                    @endif

                                    <div class="legal-editor" data-input="{{ $doc['content_key'] }}">{!! \App\Support\LegalContent::sanitize(old($doc['content_key'], $doc['html'])) !!}</div>
                                    <input type="hidden" name="{{ $doc['content_key'] }}" id="{{ $doc['content_key'] }}">

                                    @if($key === 'privacy_policy' || $key === 'terms')
                                        @php $urlKey = $key === 'privacy_policy' ? 'privacy_policy_url' : 'terms_url'; @endphp
                                        <div class="mt-3">
                                            <label class="form-label small" for="{{ $urlKey }}">Hosted somewhere else? (optional)</label>
                                            <input type="url" class="form-control form-control-sm" id="{{ $urlKey }}" name="{{ $urlKey }}" value="{{ old($urlKey, $contact[$urlKey]) }}" placeholder="Leave empty to use the page above">
                                            <small class="text-secondary">Only if your lawyer hosts this document on your own website. The app still shows the text above.</small>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="mb-3">
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bx bx-save"></i> Save Legal &amp; Support
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('admin/plugins/quill/quill.js') }}"></script>
    <script>
        (function () {
            // Only the formats the app's legal screen and the public pages can
            // show: headings, bold/italic, lists and links.
            var toolbar = [
                [{ header: [2, 3, false] }],
                ['bold', 'italic'],
                [{ list: 'bullet' }, { list: 'ordered' }],
                ['link'],
                ['clean']
            ];
            var editors = [];

            document.querySelectorAll('.legal-editor').forEach(function (el) {
                var quill = new Quill(el, {
                    theme: 'snow',
                    modules: { toolbar: toolbar },
                    formats: ['header', 'bold', 'italic', 'list', 'link']
                });
                editors.push({ quill: quill, input: document.getElementById(el.dataset.input) });
            });

            document.getElementById('legal-form').addEventListener('submit', function () {
                editors.forEach(function (e) {
                    // An editor with nothing typed still holds <p><br></p>.
                    e.input.value = e.quill.getText().trim() === '' ? '' : e.quill.root.innerHTML;
                });
            });
        })();
    </script>
@endsection
