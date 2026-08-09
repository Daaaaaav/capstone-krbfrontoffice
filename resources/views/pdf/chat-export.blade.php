<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        @page {
            margin: 2cm 2.2cm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #111;
            line-height: 1.5;
        }

        .header {
            border-bottom: 2px solid #111;
            padding-bottom: 10px;
            margin-bottom: 18px;
        }

        .header-top {
            display: table;
            width: 100%;
        }

        .header-left {
            display: table-cell;
            vertical-align: middle;
        }

        .header-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
            white-space: nowrap;
        }

        .doc-title {
            font-size: 15pt;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .doc-meta {
            font-size: 8.5pt;
            color: #555;
            margin-top: 2px;
        }

        .badge {
            display: inline-block;
            background: #111;
            color: #fff;
            font-size: 8pt;
            font-weight: bold;
            padding: 3px 9px;
            border-radius: 4px;
            letter-spacing: 0.04em;
        }

        .messages {
            margin-top: 6px;
        }

        .msg-row {
            margin-bottom: 10px;
        }

        .msg-bubble {
            display: table;
            width: 100%;
        }

        .msg-row.user .msg-bubble {
            text-align: right;
        }

        .bubble-inner {
            display: inline-block;
            max-width: 82%;
            padding: 7px 11px;
            border-radius: 10px;
            font-size: 9.5pt;
            text-align: left;
            word-wrap: break-word;
        }

        .msg-row.user .bubble-inner {
            background: #1a1a2e;
            color: #fff;
        }

        .msg-row.assistant .bubble-inner {
            background: #f2f4f7;
            color: #111;
            border: 1px solid #dde1e9;
        }

        .bubble-role {
            font-size: 7.5pt;
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .msg-row.user .bubble-role {
            color: #a0b4e8;
        }

        .msg-row.assistant .bubble-role {
            color: #5a7a9f;
        }

        .bubble-text {
            white-space: pre-wrap;
        }

        .bubble-time {
            font-size: 7pt;
            margin-top: 4px;
            opacity: 0.55;
        }

        .msg-row.user .bubble-time {
            text-align: right;
        }

        .msg-divider {
            border: none;
            border-top: 1px dashed #dde1e9;
            margin: 6px 0;
        }

        .empty {
            text-align: center;
            color: #888;
            font-size: 9pt;
            margin-top: 30px;
        }

        .doc-footer {
            margin-top: 30px;
            border-top: 1px solid #ccc;
            padding-top: 8px;
            font-size: 7.5pt;
            color: #888;
            text-align: center;
        }

        .page-num {
            position: fixed;
            bottom: -1.4cm;
            right: 0;
            font-size: 7.5pt;
            color: #aaa;
        }
    </style>
</head>
<body>

    <div class="header">
        <div class="header-top">
            <div class="header-left">
                <div class="doc-title">{{ $title }}</div>
                <div class="doc-meta">
                    Exported by: <strong>{{ $user->full_name ?? $user->name ?? 'Unknown' }}</strong>
                    &nbsp;·&nbsp;
                    {{ $exportedAt }}
                    &nbsp;·&nbsp;
                    {{ count($messages) }} message{{ count($messages) !== 1 ? 's' : '' }}
                </div>
            </div>
            <div class="header-right">
                <span class="badge">AI Assistant</span>
            </div>
        </div>
    </div>

    <div class="messages">
        @forelse ($messages as $i => $msg)
            @php
                $isUser = ($msg['role'] ?? '') === 'user';
                $roleLabel = $isUser ? 'You' : 'AI Assistant';
            @endphp

            @if ($i > 0)
                <hr class="msg-divider">
            @endif

            <div class="msg-row {{ $isUser ? 'user' : 'assistant' }}">
                <div class="msg-bubble">
                    <div class="bubble-inner">
                        <div class="bubble-role">{{ $roleLabel }}</div>
                        <div class="bubble-text">{{ $msg['text'] ?? '' }}</div>
                        @if (!empty($msg['sent_at']))
                            <div class="bubble-time">{{ $msg['sent_at'] }}</div>
                        @endif
                    </div>
                </div>
            </div>

        @empty
            <div class="empty">No messages to export.</div>
        @endforelse
    </div>

    <div class="doc-footer">
        This document was generated from the AI Assistant chat and is intended for internal use only.
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $pdf->page_text(
                510, 810,
                "Page {PAGE_NUM} of {PAGE_COUNT}",
                null, 7.5, [0.6, 0.6, 0.6]
            );
        }
    </script>

</body>
</html>
