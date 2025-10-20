@extends('layouts.admin')

@section('content')
    <!--start page wrapper -->
    <div class="page-wrapper">
        <div class="page-content">
            <!--breadcrumb-->
            <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3">
                <div class="breadcrumb-title pe-3">Tables</div>
                <div class="ps-3">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0 p-0">
                            <li class="breadcrumb-item"><a href="javascript:;"><i class="bx bx-home-alt"></i></a>
                            </li>
                            <li class="breadcrumb-item active" aria-current="page">Vendors Table</li>
                        </ol>
                    </nav>
                </div>
            </div>
            <!--end breadcrumb-->

            <h6 class="mb-0 text-uppercase">Vendor Records</h6>
            <hr />
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="example2" class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>S.no</th>
                                    <th>Name</th>
                                    <th>Business Type</th>
                                    <th>Phone Number </th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                style="background-color: #a1a1a1;">
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Harry</td>
                                    <td>Health Care</td>
                                    <td>61363636364</td>
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="statusSwitch" checked=""
                                                >
                                            <label class="form-check-label" for="statusSwitch"
                                                id="statusLabel">Active</label>
                                        </div>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-outline-secondary">
                                            <i class="bx bx-show me-0"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        document.getElementById("statusSwitch").addEventListener("change", function() {
            let label = document.getElementById("statusLabel");
            if (this.checked) {
                label.textContent = "Active";
                label.classList.remove("text-danger");
                label.classList.add("text-success");
            } else {
                label.textContent = "Inactive";
                label.classList.remove("text-success");
                label.classList.add("text-danger");
            }
        });
    </script>


    <!--end page wrapper -->
@endsection
