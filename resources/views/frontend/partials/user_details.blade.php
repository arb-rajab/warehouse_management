<div class="container">
    <div class="row">
        <div class="col-xxl-8 col-xl-10 mx-auto my-3">
            <div class="flex-grow-1 front-header-search d-flex align-items-center bg-white">
                <div class="position-relative flex-grow-1 px-3 px-lg-0">
                    <div class="d-flex position-relative align-items-center">
                        <div class="d-lg-none" data-toggle="class-toggle" data-target=".front-header-search">
                            <button class="btn px-2" type="button"><i class="la la-2x la-long-arrow-left"></i></button>
                        </div>
                        <div class="search-input-box">
                            <input type="text"
                                class="border border-soft-light form-control fs-14 hov-animate-outline"
                                id="search_customers" name="keyword" placeholder="Search user..." autocomplete="off">

                            <svg id="Group_723" data-name="Group 723" xmlns="http://www.w3.org/2000/svg" width="20.001"
                                height="20" viewBox="0 0 20.001 20">
                                <path id="Path_3090" data-name="Path 3090"
                                    d="M9.847,17.839a7.993,7.993,0,1,1,7.993-7.993A8,8,0,0,1,9.847,17.839Zm0-14.387a6.394,6.394,0,1,0,6.394,6.394A6.4,6.4,0,0,0,9.847,3.453Z"
                                    transform="translate(-1.854 -1.854)" fill="#b5b5bf"></path>
                                <path id="Path_3091" data-name="Path 3091"
                                    d="M24.4,25.2a.8.8,0,0,1-.565-.234l-6.15-6.15a.8.8,0,0,1,1.13-1.13l6.15,6.15A.8.8,0,0,1,24.4,25.2Z"
                                    transform="translate(-5.2 -5.2)" fill="#b5b5bf"></path>
                            </svg>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
    @if ($users && count($users) > 0)
        <div class="row">
            <div class="col-xxl-8 col-xl-10 mx-auto">
                <div class="border bg-white p-3 p-lg-4 text-left">
                    <div class="mb-4">
                        <!-- Headers -->
                        <div
                            class="row gutters-5 d-none d-lg-flex border-bottom mb-3 pb-3 text-secondary fs-12 text-center">

                            <div class="col-md-2 fw-600">{{ translate('ID') }}</div>
                            <div class="col col-md-2 fw-600">{{ translate('Name') }}</div>
                            <div class="col-md-2 fw-600">{{ translate('Company name') }}</div>

                            <div class="col-md-2 fw-600">{{ translate('Address') }}</div>
                            <div class="col-md-2 fw-600">{{ translate('Phone number') }}</div>
                            <div class="col-md-2 fw-600">{{ translate('Pick') }}</div>
                        </div>
                        @php
                            $total = 0;
                            $currentPage = request()->get('page', 1);
                            $perPage = 10;
                            $offset = ($currentPage - 1) * $perPage;
                            $usersArray = $users->toArray();
                            $paginatedUsers = array_slice($usersArray, $offset, $perPage, true);
                            $paginator = new \Illuminate\Pagination\LengthAwarePaginator($paginatedUsers, count($users), $perPage, $currentPage);
                            $paginator->setPath(request()->url());
                        @endphp

                        <!-- Cart Items -->
                        <ul id="user-list-container" class="list-group list-group-flush">
                            @foreach ($paginator as $key => $user)
                                <li class="list-group-item px-0 @if(auth()->user()->rep_last_open_visit()?->customer_id == $user['id']) bg-success rounded-2 @endif">
                                    <div class="row gutters-5 align-items-center text-center">
                                        <!-- User & name -->
                                        <div class="col-md-2 mb-2 mb-md-0">
                                            <span class="fs-14">{{ $user['id'] }}</span>
                                        </div>
                                        <!-- Quantity -->
                                        <div class="col-md-2 col order-1 order-md-0">
                                            <span class="fs-14">{{ $user['name'] ?? '-' }}</span>
                                        </div>
                                        <div class="col-md-2 col order-1 order-md-0">
                                            <span class="fs-14">{{ $user['company_name'] }}</span>
                                        </div>
                                        <div class="col-md-2 col order-1 order-md-0">
                                            <span
                                                class="fs-14">{{ isset($user['address']) ? $user['address'] : '-' }}</span>
                                        </div>
                                        <div class="col-md-2 col order-1 order-md-0">
                                            <span
                                                class="fs-14">{{ isset($user['phone']) ? $user['phone'] : '-' }}</span>
                                        </div>
                                        <div class="col-md-2">
                                            <a href="javascript:void(0)"
                                                onclick="chooseCustomer({{ $user['id'] }}, this)"
                                                class="btn btn-icon btn-sm btn-soft-primary bg-soft-warning hov-bg-primary btn-circle @if(auth()->user()->rep_last_open_visit()?->customer_id == $user['id']) disabled @endif">
                                                <i class="las la-check-square fs-16"></i>
                                            </a>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                        <div class="d-flex justify-content-center py-3">
                            {{ $paginator->links() }}
                        </div>
                    </div>

                    <div class="row ">
                        <!-- Return to home -->
                        <div class="col-md-12 text-center text-md-right">
                            <a href="{{ route('home') }}" id="cart_index"
                                class="btn btn-primary fs-14 fw-700 rounded-0 px-4">
                                {{ translate('Continue to visit details') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row">
            <div class="col-xl-8 mx-auto">
                <div class="border bg-white p-4">
                    <!--  -->
                    <div class="text-center p-3">
                        <i class="las la-frown la-3x opacity-60 mb-3"></i>
                        <h3 class="h4 fw-700">{{ translate('No customers are available at the moment.') }}</h3>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>

<script type="text/javascript">
    AIZ.extra.plusMinus();
</script>

<script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>

<script type="text/javascript">
    $(document).ready(function() {
        $('#search_customers').on('input', function() {
            var query = $(this).val();

            $.ajax({
                url: '{{ route('search.users') }}',
                method: 'GET',
                data: {
                    query: query
                },
                dataType: 'json',
                success: function(data) {
                    updateUsersList(data.users, data);
                },
                error: function(error) {
                    console.log('Error:', error);
                }
            });
        });

        function updateUsersList(users, data) {
            var userListHtml = '';
            $('#user-list-container').empty();

            if (users.length == 0) {
                var noUserHtml = '<li class="list-group-item px-0 text-center">';
                noUserHtml += '<h2 class="fs-16 fw-600 d-none d-lg-block opacity-50">No users found.</h2>';
                noUserHtml += '</li>';
                $('#user-list-container').append(noUserHtml);
            } else {
                $.each(users, function(index, user) {
                    var userListHtml;

                    if({{ $carts->first()->for_customer ?? 0 }} == user.id){
                        userListHtml = '<li class="list-group-item px-0 bg-success rounded-2">';

                    }else{
                        userListHtml = '<li class="list-group-item px-0">';
                    }

                    userListHtml += '<div class="row gutters-5 align-items-center text-center">';
                    userListHtml += '<div class="col-md-2 mb-2 mb-md-0">';
                    userListHtml += '<span class="fs-14">' + user.id + '</span>';
                    userListHtml += '</div>';
                    userListHtml += '<div class="col-md-2 col order-1 order-md-0">';
                    userListHtml += '<span class="fs-14">' + (user.name ? user.name : '-') + '</span>';
                    userListHtml += '</div>';
                    userListHtml += '<div class="col-md-2 col order-1 order-md-0">';
                    userListHtml += '<span class="fs-14">' + user.company_name + '</span>';
                    userListHtml += '</div>';
                    userListHtml += '<div class="col-md-2 col order-1 order-md-0">';
                    userListHtml += '<span class="fs-14">' + (user.address ? user.address : '-') + '</span>';
                    userListHtml += '</div>';
                    userListHtml += '<div class="col-md-2 col order-1 order-md-0">';
                    userListHtml += '<span class="fs-14">' + (user.phone ? user.phone : '-') + '</span>';
                    userListHtml += '</div>';
                    userListHtml += '<div class="col-md-2">';

                    if({{ $carts->first()->for_customer ?? 0 }} == user.id){
                        userListHtml += '<a href="javascript:void(0)" onclick="chooseCustomer(' + user.id +
                            ', this)" class="btn btn-icon btn-sm btn-soft-primary bg-soft-warning hov-bg-primary btn-circle disabled">';
                    } else{
                        userListHtml += '<a href="javascript:void(0)" onclick="chooseCustomer(' + user.id +
                            ', this)" class="btn btn-icon btn-sm btn-soft-primary bg-soft-warning hov-bg-primary btn-circle">';
                    }

                    userListHtml += '<i class="las la-check-square fs-16"></i>';
                    userListHtml += '</a>';
                    userListHtml += '</div>';
                    userListHtml += '</div>';
                    userListHtml += '</li>';

                    $('#user-list-container').append(userListHtml);
                });
            }
        }
    });
</script>
