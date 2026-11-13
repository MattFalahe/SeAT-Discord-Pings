@if($row->isEmergencyRequest())
    <span class="badge badge-danger">Emergency</span>
@elseif($row->isMemberRequest())
    <span class="badge badge-info">Request</span>
@endif
