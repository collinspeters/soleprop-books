<?php

return [

    'payment_received'      => 'Payment Received',
    'payment_made'          => 'Payment Made',
    'paid_by'               => 'Paid By',
    'paid_to'               => 'Paid To',
    'related_invoice'       => 'Related Invoice',
    'related_bill'          => 'Related Bill',
    'recurring_income'      => 'Recurring Income',
    'recurring_expense'     => 'Recurring Expense',
    'included_tax'          => 'Included tax amount',
    'connected'             => 'Connected',

    'form_description' => [
        'general'           => 'Here you can enter the general information of transaction such as date, amount, account, description, etc.',
        'assign_income'     => 'Select a category and customer to make your reports more detailed.',
        'assign_expense'    => 'Select a category and vendor to make your reports more detailed.',
        'other'             => 'Enter a reference to keep the transaction linked to your records.',
    ],

    'slider' => [
        'create'            => ':user created this transaction on :date',
        'attachments'       => 'Download the files attached to this transaction',
        'create_recurring'  => ':user created this recurring template on :date',
        'schedule'          => 'Repeat every :interval :frequency since :date',
        'children'          => ':count transactions were created automatically',
        'connect'           => 'This transaction is connected to :count transactions',
        'transfer_headline' => 'From :from_account to :to_account',
        'transfer_desc'     => 'Transfer created on :date.',
    ],

    'share' => [
        'income' => [
            'show_link'     => 'Your customer can view the transaction at this link',
            'copy_link'     => 'Copy the link and share it with your customer.',
        ],

        'expense' => [
            'show_link'     => 'Your vendor can view the transaction at this link',
            'copy_link'     => 'Copy the link and share it with your vendor.',
        ],
    ],

    'sticky' => [
        'description'       => 'You are previewing how your customer will see the web version of your payment.',
    ],

    'ai_review_title'                   => 'AI Transaction Review',
    'total_ai_suggestions'              => 'Total AI Suggestions',
    'low_confidence'                    => 'Low Confidence',
    'unreviewed'                        => 'Unreviewed',
    'reviewed'                          => 'Reviewed',
    'filter_type'                       => 'Filter Type',
    'confidence_threshold'              => 'Confidence Threshold',
    'all_ai_suggestions'                => 'All AI Suggestions',
    'approve_all_selected'              => 'Approve All Selected',
    'mark_as_reviewed'                  => 'Mark as Reviewed',
    'ai_suggestions_requiring_review'   => 'AI Suggestions Requiring Review',
    'current_category'                  => 'Current Category',
    'ai_suggested_category'             => 'AI Suggested Category',
    'confidence'                        => 'Confidence',
    'pending_review'                    => 'Pending Review',
    'approve'                           => 'Approve',
    'override'                          => 'Override',
    'already_reviewed'                  => 'Already Reviewed',
    'no_ai_suggestions'                 => 'No AI Suggestions Found',
    'no_ai_suggestions_description'     => 'There are no transactions with AI category suggestions matching your current filters.',
    'override_category'                 => 'Override Category',
    'select_category'                   => 'Select Category',
    'please_select_transactions'        => 'Please select at least one transaction.',
    'ai_suggestion_approved'            => 'AI suggestion approved successfully.',
    'ai_suggestion_overridden'          => 'AI suggestion overridden successfully.',
    'bulk_ai_review_completed'          => 'Bulk review completed for :count transactions.',

];
