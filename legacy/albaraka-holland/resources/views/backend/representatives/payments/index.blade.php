@extends('backend.layouts.app')

@section('content')
    <style>
        .blurred-row {
            background-color: #f2f2f2;
        }
    </style>

    @php
        use Carbon\Carbon;

    @endphp
    <div class="aiz-titlebar text-left mt-2 mb-3">
        <div class="align-items-center">
            <h1 class="h3">{{ translate('All Payments') }}</h1>
        </div>
    </div>


    <div class="card">
        <form class="" id="sort_payments" action="" method="GET">
            <div class="card-header row gutters-5">
                <div class="col">
                    <h5 class="mb-0 h6">{{ translate('Payments') }}</h5>
                </div>

                <div class="dropdown mb-2 mb-md-0 ml-1">
                    <button type="button" class="btn border menu-dropdown dropdown-toggle" data-toggle="dropdown">
                        {{ translate('Filter') }}
                    </button>
                    <div class="dropdown-menu dropdown-menu-right dropdown-menu-sub-dropdown" onclick="stopPropagation(event)">
                        <div class="px-3 py-2">
                            <div class="d-flex align-items-center">
                                <span class="fs-5 text-dark fw-bold mr-2">{{ translate('Filter Options') }}</span>
                            </div>
                        </div>


                        <div class="separator border-gray-200"></div>

                        <div class="px-3 py-2">
                            <div class="mb-10 py-2">
                                <div class="py-1" >
                                    <label class="aiz-switch aiz-switch-success px-2 " style="font-size:15px;" for="pending">{{ translate('Pending') }}
                                        <input type="checkbox" name="pending" id="pending" class="mr-2 check-one filter" @if (request()->has('pending')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="py-1" >
                                    <label class="aiz-switch aiz-switch-success px-2 " style="font-size:15px;" for="accepted">{{ translate('Accepted') }}
                                        <input type="checkbox" name="accepted" id="accepted" class="mr-2 check-one filter" @if (request()->has('accepted')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                                <div class="py-1">
                                    <label class="aiz-switch aiz-switch-success  px-2 " style="font-size:15px;" for="rejected">{{ translate('Rejected') }}
                                        <input type="checkbox" name="rejected" id="rejected" class="mr-2 check-one filter" @if (request()->has('rejected')) checked @endif>
                                        <span class="slider round"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">{{ translate('Apply') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group mb-0">
                        <input type="text" class="form-control" id="search"
                            name="search" value="{{ request()->input('search') ?? '' }}"
                            placeholder="{{ translate('Type name & Enter') }}">
                    </div>
                </div>
            </div>

            <div class="card-body">
                <table class="table aiz-table mb-0">
                    <thead>
                        <tr>
                            <!--<th data-breakpoints="lg">#</th>-->
                            <th>
                                <div class="form-group">
                                    <div class="aiz-checkbox-inline">
                                        <label class="aiz-checkbox">
                                            <input type="checkbox" class="check-all">
                                            <span class="aiz-square-check"></span>
                                        </label>
                                    </div>
                                </div>
                            </th>
                            <th>{{ translate('ID') }}</th>
                            <th>{{ translate('Customer Name') }}</th>
                            <th>{{ translate('Customer Otajer ID') }}</th>
                            <th>{{ translate('Representative Name') }}</th>
                            <th>{{ translate('Receipt Code') }}</th>
                            <th>{{ translate('Invoice Code') }}</th>
                            <th>{{ translate('Amount') }}</th>
                            <th>{{ translate('Accountant') }}</th>
                            <th>{{ translate('Notes') }}</th>
                            <th class="text-center">{{ translate('Status') }}</th>
                            <th>{{ translate('Options') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($payments as $key => $payment)
                            @if ($payment != null)
                                <tr class="{{ $payment->status != 'pending' ? 'blurred-row' : '' }}">
                                    <!--<td>{{ $key + 1 + ($payments->currentPage() - 1) * $payments->perPage() }}</td>-->
                                    <td>
                                        <div class="form-group">
                                            <div class="aiz-checkbox-inline">
                                                <label class="aiz-checkbox">
                                                    <input type="checkbox" class="check-one" name="id[]"
                                                        value="{{ $payment->id }}">
                                                    <span class="aiz-square-check"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $payment->id }}</td>
                                    <td>
                                        {{ $payment->customer?->name }}
                                    </td>
                                    <td>{{ $payment->customer?->AccSysID }}</td>
                                    <td>{{ $payment->rep?->name }}</td>
                                    <td>{{ $payment->receipt_code }}</td>
                                    <td>{{ $payment->invoice_code }}</td>
                                    <td>{{ $payment->amount }}</td>
                                    <td>{{ $payment->admin?->name }}</td>
                                    <td>{{ $payment->notes }}</td>
                                    @if ($payment->status == 'pending')
                                        <td class="text-center align-middle">
                                            <a class="btn btn-soft-success btn-icon btn-circle btn-sm"
                                                href="{{ route('admin.rep.payments.accept', ['id' => $payment->id]) }}"
                                                title="{{ translate('Accept') }}">
                                                <i class="las la-check"></i>
                                            </a>
                                            <a class="btn btn-soft-danger btn-icon btn-circle btn-sm"
                                                href="{{ route('admin.rep.payments.reject', ['id' => $payment->id]) }}"
                                                title="{{ translate('Reject') }}">
                                                <i class="las la-times"></i>
                                                </a>
                                        </td>
                                    @else
                                        <td class="text-center align-middle">
                                            @if ($payment->status == 'pending')
                                                <div class="badge badge-danger">
                                                    <i class="las la-clock"></i>
                                                </div>
                                            @elseif ($payment->status == 'accepted')
                                                <div class="badge badge-success">
                                                    <i class="las la-check"></i>
                                                </div>
                                            @else
                                                <div class="badge badge-danger">
                                                    <i class="las la-times"></i>
                                                </div>
                                            @endif
                                        </td>

                                    @endif

                                    <td>
                                        <div class="dropdown mb-2 mb-md-0 ">
                                            <button class="btn btn-sm border dropdown-toggle" type="button"
                                                data-toggle="dropdown">
                                                {{ translate('Actions') }}
                                            </button>

                                            <div class="dropdown-menu dropdown-menu-right">
                                                <a href="{{ route('admin.rep.payments.export', $payment->id) }}" class="dropdown-item"
                                                    data-href="{{ route('admin.rep.payments.export', $payment->id) }}"
                                                    title="{{ translate('Export') }}">
                                                    {{ translate('Export') }}
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
                <div class="aiz-pagination">
                    {{ $payments->appends(request()->input())->links() }}
                </div>
            </div>
        </form>
    </div>


    <div class="modal fade" id="confirm-ban">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h6">{{ translate('Confirmation') }}</h5>
                    <button type="button" class="close" data-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>{{ translate('Do you really want to ban this Customer?') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <a type="button" id="confirmation" class="btn btn-primary">{{ translate('Proceed!') }}</a>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="confirm-unban">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title h6">{{ translate('Confirmation') }}</h5>
                    <button type="button" class="close" data-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>{{ translate('Do you really want to unban this Customer?') }}</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal">{{ translate('Cancel') }}</button>
                    <a type="button" id="confirmationunban" class="btn btn-primary">{{ translate('Proceed!') }}</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('modal')
    @include('modals.delete_modal')


     <!-- Package Modal -->
     <div class="modal fade" id="package_modal">
		<div class="modal-dialog">
			<div class="modal-content" id="package-modal-content">

			</div>
		</div>
	</div>
@endsection

@section('script')
    <script type="text/javascript">
        $(document).on("change", ".check-all", function() {
            if (this.checked) {
                // Iterate each checkbox
                $('.check-one:checkbox').each(function() {
                    this.checked = true;
                });
            } else {
                $('.check-one:checkbox').each(function() {
                    this.checked = false;
                });
            }

        });

        function sort_payments(el) {
            $('#sort_payments').submit();
        }

        function stopPropagation(event) {
            event.stopPropagation();
        }

        $(document).ready(function() {
            $('#all_customers').change(function() {
                // Disable or enable other checkboxes based on the "all" checkbox state
                $('.filter').not(this).prop('disabled', this.checked);

                // Remove the 'checked' attribute from other checkboxes
                if (this.checked) {
                    $('.check-one').not(this).prop('checked', false);
                }
            });
        });
    </script>

@endsection
