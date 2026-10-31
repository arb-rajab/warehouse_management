@extends('shop.frontend.layouts.user_panel')

@section('panel_content')
    <div class="aiz-titlebar mb-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h1 class="fs-20 fw-700 text-dark">{{ __('storefront.support.support_ticket') }}</h1>
            </div>
        </div>
    </div>

    <!-- Create a Ticket -->
    <div class="p-4 mb-3 c-pointer text-center bg-light has-transition border h-100 hov-bg-soft-light" data-toggle="modal" data-target="#ticket_modal">
        <i class="las la-plus la-3x mb-2"></i>
        <div class="fs-14 fw-600 text-dark">{{ __('storefront.support.create_a_ticket') }}</div>
    </div>

    <!-- Tickets -->
    <div class="card rounded-0 shadow-none border">
        <div class="card-header border-bottom-0">
            <h5 class="mb-0 fs-20 fw-700 text-dark text-center text-md-left">{{ __('storefront.support.tickets')}}</h5>
        </div>
          <div class="card-body py-0">
              <table class="table aiz-table mb-4">
                  <thead class="text-gray fs-12">
                      <tr>
                          <th data-breakpoints="lg" class="pl-0">{{ __('storefront.support.ticket_id') }}</th>
                          <th data-breakpoints="lg">{{ __('storefront.common.sending_date') }}</th>
                          <th>{{ __('storefront.common.subject')}}</th>
                          <th>{{ __('storefront.common.status')}}</th>
                          <th data-breakpoints="lg" class="text-right pr-0">{{ __('storefront.common.options')}}</th>
                      </tr>
                  </thead>
                  <tbody class="fs-14">
                      @foreach ($tickets as $key => $ticket)
                          <tr>
                              <td class="pl-0 fw-700">#{{ $ticket->code }}</td>
                              <td>{{ \App\Support\StoreLocale::date(strtotime($ticket->created_at), true) }}</td>
                              <td>{{ $ticket->subject }}</td>
                              <td>
                                  @if ($ticket->status == 'pending')
                                      <span class="badge badge-inline badge-danger p-3 fs-12" style="border-radius: 25px; min-width: 80px !important;">{{ __('storefront.common.pending')}}</span>
                                  @elseif ($ticket->status == 'open')
                                      <span class="badge badge-inline badge-secondary p-3 fs-12" style="border-radius: 25px; min-width: 80px !important;">{{ __('storefront.common.open')}}</span>
                                  @else
                                      <span class="badge badge-inline badge-success p-3 fs-12" style="border-radius: 25px; min-width: 80px !important;">{{ __('storefront.common.solved')}}</span>
                                  @endif
                              </td>
                              <td class="text-right pr-0">
                                  <a href="{{route('support_ticket.show', encrypt($ticket->id))}}" class="btn btn-styled btn-link fw-700 py-1 px-0 icon-anim text-decoration-none">
                                      {{ __('storefront.common.view_details')}}
                                      <i class="la la-angle-right fw-900 text-sm"></i>
                                  </a>
                              </td>
                          </tr>
                      @endforeach
                  </tbody>
              </table>
              <!-- Pagination -->
              <div class="aiz-pagination">
                  {{ $tickets->links() }}
              </div>
          </div>
    </div>
@endsection

@section('modal')
    <!-- Ticket modal -->
    <div class="modal fade" id="ticket_modal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-md" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title strong-600 heading-5">{{ __('storefront.support.create_a_ticket')}}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ __('storefront.common.close') }}">
                      <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body px-3 pt-3">
                    <form class="" action="{{ route('support_ticket.store') }}" method="post" enctype="multipart/form-data">
                        @csrf
                        @php $keepInput = $errors->storeTicket->any(); @endphp
                        <x-required-legend />
                        <div class="row">
                            <div class="col-md-2">
                                <label for="ticket_subject">{{ __('storefront.common.subject')}}<x-required-mark /></label>
                            </div>
                            <div class="col-md-10 mb-3">
                                <input type="text" id="ticket_subject" class="form-control rounded-0 @error('subject', 'storeTicket') is-invalid @enderror" placeholder="{{ __('storefront.common.subject')}}" name="subject" value="{{ $keepInput ? old('subject') : '' }}" maxlength="255" required>
                                @error('subject', 'storeTicket')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-2">
                                <label for="ticket_details">{{ __('storefront.common.provide_a_detailed_description')}}<x-required-mark /></label>
                            </div>
                            <div class="col-md-10 mb-3">
                                <textarea type="text" id="ticket_details" class="form-control rounded-0 @error('details', 'storeTicket') is-invalid @enderror" rows="3" name="details" placeholder="{{ __('storefront.support.type_your_reply')}}" data-buttons="bold,underline,italic,|,ul,ol,|,paragraph,|,undo,redo" maxlength="10000" required>{{ $keepInput ? old('details') : '' }}</textarea>
                                @error('details', 'storeTicket')
                                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                        <div class="form-group row">
                            <label class="col-md-2 col-form-label">{{ __('storefront.common.photo') }}</label>
                            <div class="col-md-10">
                                <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                                    <div class="input-group-prepend">
                                        <div class="input-group-text bg-soft-secondary font-weight-medium rounded-0">{{ __('storefront.upload.browse')}}</div>
                                    </div>
                                    <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                                    <input type="hidden" name="attachments" class="selected-files" value="{{ $keepInput && is_string(old('attachments')) ? old('attachments') : '' }}">
                                </div>
                                <div class="file-preview box sm">
                                </div>
                                @error('attachments', 'storeTicket')
                                    <span class="invalid-feedback d-block" role="alert"><strong>{{ $message }}</strong></span>
                                @enderror
                            </div>
                        </div>
                        <div class="text-right mt-4">
                            <button type="button" class="btn btn-secondary rounded-0 w-150px" data-dismiss="modal">{{ __('storefront.common.cancel')}}</button>
                            <button type="submit" class="btn btn-primary rounded-0 w-150px">{{ __('storefront.support.send_ticket')}}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('script')
    @if ($errors->storeTicket->any())
        <script type="text/javascript">
            $(function () { $('#ticket_modal').modal('show'); });
        </script>
    @endif
@endsection
