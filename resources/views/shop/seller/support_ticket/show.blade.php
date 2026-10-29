@extends('shop.seller.layouts.app')

@section('panel_content')
    <div class="card">
        <div class="card-header row gutters-5">
            <div class="text-center text-md-left">
                <h5 class="mb-md-0 h5">{{ $ticket->subject }} #{{ $ticket->code }}</h5>
               <div class="mt-2">
                   <span> {{ $ticket->user->name }} </span>
                   <span class="ml-2"> {{ $ticket->created_at }} </span>
                   <span class="badge badge-inline badge-secondary ml-2"> {{ \App\Support\StoreLocale::status($ticket->status) }} </span>
               </div>
            </div>
        </div>
        <div class="card-body">
            <form action="{{route('seller.support_ticket.reply_store')}}" method="POST" enctype="multipart/form-data">
                @csrf
                <x-required-legend />
                <input type="hidden" name="ticket_id" value="{{$ticket->id}}" required>
                @error('ticket_id')
                    <small class="form-text text-danger">{{ $message }}</small>
                @enderror
                <div class="form-group">
                    <label for="seller-ticket-reply">{{ __('storefront.support.type_your_reply') }}<x-required-mark /></label>
                    <textarea class="aiz-text-editor" name="reply" id="seller-ticket-reply" maxlength="10000" data-buttons='[["font", ["bold", "underline", "italic"]],["para", ["ul", "ol"]],["view", ["undo","redo"]]]' required>{{ is_string(old('reply')) ? old('reply') : '' }}</textarea>
                    @error('reply')
                        <small class="form-text text-danger">{{ $message }}</small>
                    @enderror
                </div>
                <div class="form-group row">
                    <div class="col-md-12">
                        <div class="input-group" data-toggle="aizuploader" data-type="image" data-multiple="true">
                            <div class="input-group-prepend">
                                <div class="input-group-text bg-soft-secondary font-weight-medium">{{ __('storefront.upload.browse')}}</div>
                            </div>
                            <div class="form-control file-amount">{{ __('storefront.upload.choose_file') }}</div>
                            <input type="hidden" name="attachments" class="selected-files" value="{{ is_string(old('attachments')) ? old('attachments') : '' }}">
                        </div>
                        <div class="file-preview box sm">
                        </div>
                        @error('attachments')
                            <small class="form-text text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                </div>
                <div class="form-group mb-0 text-right">
                    <button type="submit" class="btn btn-sm btn-primary" onclick="submit_reply('pending')">{{ __('storefront.support.send_reply') }}</button>
                </div>
            </form>
            <div class="pad-top">
                <ul class="list-group list-group-flush">
                    @foreach($ticket->ticketreplies as $ticketreply)
                        <li class="list-group-item px-0">
                            <div class="media">
                                <a class="media-left" href="#">
                                    @if($ticketreply->user->avatar_original != null)
                                        <span class="avatar avatar-sm mr-3">
                                            <img src="{{ uploaded_asset($ticketreply->user->avatar_original) }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/avatar-place.png') }}';">
                                        </span>
                                    @else
                                        <span class="avatar avatar-sm mr-3">
                                            <img src="{{ static_asset('assets/img/avatar-place.png') }}">
                                        </span>
                                    @endif
                                </a>
                                <div class="media-body">
                                    <div class="comment-header">
                                        <span class="text-bold h6 text-muted">{{ $ticketreply->user->name }}</span>
                                        <p class="text-muted text-sm fs-11">{{$ticketreply->created_at}}</p>
                                    </div>
                                </div>
                            </div>
                            <div>
                                {!! clean_html($ticketreply->reply) !!}
                                <br>
                                @foreach ((explode(",",$ticketreply->files)) as $key => $file)
                                    @php $file_detail = \App\Models\Upload::where('id', $file)->first(); @endphp
                                    @if($file_detail != null)
                                        <a href="{{ uploaded_asset($file) }}" download="" class="badge badge-lg badge-inline badge-light mb-1">
                                            <i class="las la-download text-muted">{{ $file_detail->file_original_name.'.'.$file_detail->extension }}</i>
                                        </a>
                                        <br>
                                    @endif
                                @endforeach
                            </div>
                        </li>
                    @endforeach
                    <li class="list-group-item px-0">
                        <div class="media">
                            <a class="media-left" href="#">
                                @if($ticket->user->avatar_original != null)
                                    <span class="avatar avatar-sm mr-3">
                                        <img src="{{ uploaded_asset($ticket->user->avatar_original) }}" onerror="this.onerror=null;this.src='{{ static_asset('assets/img/avatar-place.png') }}';">
                                    </span>
                                @else
                                    <span class="avatar avatar-sm mr-3">
                                        <img src="{{ static_asset('assets/img/avatar-place.png') }}">
                                    </span>
                                @endif
                            </a>
                            <div class="media-body">
                                <div class="comment-header">
                                    <span class="text-bold h6 text-muted">{{ $ticket->user->name }}</span>
                                    <p class="text-muted text-sm fs-11">{{ $ticket->created_at }}</p>
                                </div>
                            </div>
                        </div>
                        <div>
                            {!! clean_html($ticket->details) !!}
                            <br>
                            @foreach ((explode(",",$ticket->files)) as $key => $file)
                                @php $file_detail = \App\Models\Upload::where('id', $file)->first(); @endphp
                                @if($file_detail != null)
                                    <a href="{{ uploaded_asset($file) }}" download="" class="badge badge-lg badge-inline badge-light mb-1">
                                        <i class="las la-download text-muted">{{ $file_detail->file_original_name.'.'.$file_detail->extension }}</i>
                                    </a>
                                    <br>
                                @endif
                            @endforeach
                        </div>
                    </li>
                </ul>
            </div>
        </div>
    </div>
@endsection
