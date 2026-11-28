<?php

namespace App\Ksef;

enum SubmissionState: string
{
    /** KSeF has the document and is still checking it. */
    case Processing = 'processing';

    /** KSeF stored the invoice and gave it a KSeF number. */
    case Accepted = 'accepted';

    /** KSeF refused the invoice for good. */
    case Rejected = 'rejected';
}
