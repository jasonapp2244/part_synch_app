<!--sidebar wrapper -->
<div class="sidebar-wrapper" data-simplebar="true">
    <div class="sidebar-header">
        <div>
            {{-- <img src="     assets/images/logo-icon.png" class="logo-icon" alt="logo icon"> --}}
        </div>
        <div>
            <h4 class="logo-text">Part Synch</h4>
        </div>
        <div class="toggle-icon ms-auto"><i class='bx bx-arrow-back'></i>
        </div>
    </div>
    <!--navigation-->
   <ul class="metismenu" id="menu">

    <li>
        <a href="{{ route('admin.dashboard') }}">
            <div class="parent-icon"><i class='bx bx-grid-alt'></i></div>
            <div class="menu-title">Dashboard</div>
        </a>
    </li>

    <li>
        <a href="javascript:;" class="has-arrow">
            <div class="parent-icon"><i class='bx bx-store-alt'></i></div>
            <div class="menu-title">Vendors</div>
        </a>
        <ul>
            <li><a href="{{ route('vendor.records') }}"><i class='bx bx-radio-circle'></i>View Vendors</a></li>
        </ul>
    </li>

    <li>
        <a href="javascript:;" class="has-arrow">
            <div class="parent-icon"><i class='bx bx-user'></i></div>
            <div class="menu-title">Users</div>
        </a>
        <ul>
            <a href="{{ route('user.records') }}"><i class='bx bx-radio-circle'></i>View Users</a></li>
        </ul>
    </li>

    <li>
        <a href="javascript:;" class="has-arrow">
            <div class="parent-icon"><i class='bx bx-star'></i></div>
            <div class="menu-title">Featured</div>
        </a>
        <ul>
            <li><a href="{{ route('featured.records') }}"><i class='bx bx-radio-circle'></i>View Featured</a></li>
        </ul>
    </li>

    <li>
        <a href="javascript:;" class="has-arrow">
            <div class="parent-icon"><i class='bx bx-category'></i></div>
            <div class="menu-title">Category</div>
        </a>
        <ul>
            <li><a href="#"><i class='bx bx-radio-circle'></i>View Category</a></li>
        </ul>
    </li>

    <li>
        <a href="javascript:;" class="has-arrow">
            <div class="parent-icon"><i class='bx bx-subdirectory-right'></i></div>
            <div class="menu-title">Sub Category</div>
        </a>
        <ul>
            <li><a href="#"><i class='bx bx-radio-circle'></i>View Sub Category</a></li>
        </ul>
    </li>

    <li>
        <a href="javascript:;" class="has-arrow">
            <div class="parent-icon"><i class='bx bx-buildings'></i></div>
            <div class="menu-title">Company</div>
        </a>
        <ul>
            <li><a href="#"><i class='bx bx-radio-circle'></i>View Company</a></li>
        </ul>
    </li>

    <li>
        <a href="javascript:;" class="has-arrow">
            <div class="parent-icon"><i class='bx bx-wallet'></i></div>
            <div class="menu-title">Earning</div>
        </a>
        <ul>
            <li><a href="#"><i class='bx bx-radio-circle'></i>View Earning</a></li>
        </ul>
    </li>

    <li>
        <a href="javascript:;" class="has-arrow">
            <div class="parent-icon"><i class='bx bx-box'></i></div>
            <div class="menu-title">Products</div>
        </a>
        <ul>
            <li><a href="#"><i class='bx bx-radio-circle'></i>View Products</a></li>
        </ul>
    </li>

</ul>

    <!--end navigation-->
</div>
<!--end sidebar wrapper -->
