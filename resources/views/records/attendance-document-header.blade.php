<table class="document-header" aria-label="OLSHCO attendance document header">
    <colgroup>
        <col class="document-school-column">
        <col class="document-label-column">
        <col>
    </colgroup>
    <tbody>
        <tr>
            <td class="school-identity" rowspan="2">
                <div class="school-identity-content">
                    <img src="{{ $sealUrl ?? asset('olshco-seal.jpg') }}" alt="OLSHCO seal">
                    <div>
                        <strong>OLSHCO</strong>
                        <span>Our Lady of the Sacred Heart<br>College of Guimba, Inc.</span>
                    </div>
                </div>
            </td>
            <th scope="row">Year / Block</th>
            <td class="document-value">{{ $yearBlock }}</td>
        </tr>
        <tr>
            <th scope="row">Event Title</th>
            <td class="document-value">{{ $eventTitle }}</td>
        </tr>
        <tr>
            <td class="document-department">College / Department:<br><strong>{{ $department }}</strong></td>
            <th scope="row">Event Date</th>
            <td class="document-value">{{ $eventDate }}</td>
        </tr>
        <tr>
            <td class="document-title" colspan="2">ATTENDANCE RECORD</td>
            <td class="document-page">Page {{ $pageNumber }} of {{ $pageCount }}</td>
        </tr>
    </tbody>
</table>
