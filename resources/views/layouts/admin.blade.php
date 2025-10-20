<!doctype html>
<html lang="en">

{{-- Include Navbar --}}
@include('layouts.partials.header_script')


<body>
    <!--wrapper-->
    <div class="wrapper">

        {{-- Include sidebar --}}
        @include('layouts.partials.sidebar')

        {{-- Include header --}}
        @include('layouts.partials.header')


        {{-- Include content pending --}}
        @yield('content')

        {{-- Include Footer --}}
        @include('layouts.partials.footer')

    </div>
    <!--end wrapper-->

    <!--start switcher pending-->

    @include('layouts.partials.footer_script')

</body>

</html>
