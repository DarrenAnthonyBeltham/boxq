<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; margin: 0; padding: 40px 20px;">
    
    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); overflow: hidden;">
        
        <tr>
            <td style="background-color: #0f172a; padding: 24px 32px; text-align: center;">
                <h1 style="color: #ffffff; margin: 0; font-size: 24px; font-weight: bold; letter-spacing: 1px;">BoxQ Procurement</h1>
            </td>
        </tr>

        <tr>
            <td style="padding: 32px;">
                <h2 style="margin-top: 0; color: #1e293b; font-size: 20px;">Approval Required</h2>
                <p style="color: #475569; font-size: 16px; line-height: 1.5; margin-bottom: 24px;">
                    <strong>{{ $requisition->requester }}</strong> has submitted a purchase request that requires your review.
                </p>
                
                <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f1f5f9; border-radius: 8px; margin-bottom: 32px;">
                    <tr>
                        <td style="padding: 20px;">
                            <p style="margin: 0 0 10px 0; color: #334155; font-size: 15px;"><strong>Department:</strong> {{ $requisition->department }}</p>
                            <p style="margin: 0 0 10px 0; color: #334155; font-size: 15px;"><strong>Total Cost:</strong> {{ $requisition->currency === 'USD' ? '$' : 'Rp' }}{{ number_format($requisition->total_price, 2) }}</p>
                            <p style="margin: 0; color: #334155; font-size: 15px;"><strong>Justification:</strong> {{ $requisition->justification }}</p>
                        </td>
                    </tr>
                </table>

                <p style="color: #475569; font-size: 16px; margin-bottom: 24px; text-align: center;">
                    Please select an action below to process this request.
                </p>

                <table width="100%" cellpadding="0" cellspacing="0" role="presentation">
                    <tr>
                        <td align="center" style="padding-bottom: 20px;">
                            <a href="{{ $approveLink }}" style="display: inline-block; background-color: #10b981; color: #ffffff !important; text-decoration: none; font-weight: bold; font-size: 16px; padding: 14px 32px; border-radius: 6px; margin-right: 10px; min-width: 120px; text-align: center;">
                                Approve
                            </a>
                            <a href="{{ $rejectLink }}" style="display: inline-block; background-color: #ef4444; color: #ffffff !important; text-decoration: none; font-weight: bold; font-size: 16px; padding: 14px 32px; border-radius: 6px; margin-left: 10px; min-width: 120px; text-align: center;">
                                Reject
                            </a>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        <tr>
            <td style="background-color: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                <p style="margin: 0; color: #94a3b8; font-size: 13px;">
                    This is an automated message from your BoxQ System.<br>You can approve or reject directly from this email without logging in.
                </p>
            </td>
        </tr>
    </table>

</body>
</html>