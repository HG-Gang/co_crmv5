{{--
Created by PhpStorm.
Project name co_crmv5.
User: Huang Gang
Date: 2026/08/07
Time: 21:05
--}}
@if(app()->environment('testing') && request()->query('visual_audit') === '1')
    <script src="{{ asset('/js/testing/visual-audit-fixture.js') }}?v=2026080701"></script>
@endif
