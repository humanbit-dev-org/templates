@if (!empty($params))
    <div class="api-sandbox-params">
        <h4 class="api-sandbox-subtitle">{{ $title }}</h4>
        <div class="api-sandbox-table-wrap">
            <table class="api-sandbox-table">
                <thead>
                    <tr>
                        <th>Parametro</th>
                        <th>Tipo</th>
                        <th>Descrizione</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($params as $param)
                        <tr>
                            <td>
                                <code class="api-sandbox-param-name">{{ $param['name'] }}</code>
                                @if ($param['required'] ?? false)
                                    <span class="api-sandbox-required">*</span>
                                @endif
                            </td>
                            <td>{{ $param['type'] }}</td>
                            <td>{{ $param['description'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
