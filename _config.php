<?php

use SilverStripe\Reports\Report;
use SilverStripe\Subsites\Reports\SubsiteReportWrapper;

Report::add_excluded_reports(SubsiteReportWrapper::class);
