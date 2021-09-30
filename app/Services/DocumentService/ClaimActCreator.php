<?php

namespace App\Services\DocumentService;

use PhpOffice\PhpWord\TemplateProcessor;

class ClaimActCreator extends TemplatedDocumentCreator
{
    public function documentName(): string
    {
        return 'claim-act.docx';
    }

    /** @phpcsSuppress SlevomatCodingStandard.Functions.UnusedParameter */
    protected function fillTemplate(TemplateProcessor $templateProcessor): void
    {
        // не заполняется
    }

    protected function resultDocSuffix(): string
    {
        return '';
    }
}
