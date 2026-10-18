/**
 * Timeminator – User survey as a Google Form (Apps Script generator, EN)
 * =====================================================================
 * Builds the complete English feedback form automatically in your Google
 * Workspace (xixum.ai) — anonymous and open to external (e.g. LinkedIn)
 * respondents. English counterpart of google-form-generator.gs.
 *
 * HOW TO RUN:
 *  1. Go to https://script.google.com (signed in with your xixum.ai account)
 *  2. "New project" -> paste the whole content of this file
 *  3. Pick the function "buildTimeminatorSurveyEN" at the top -> Run
 *  4. Approve permissions on first run (it's your own script)
 *  5. The execution log prints two links:
 *       "EDIT:" -> tweak the form
 *       "LIVE / SHARE:" -> this link goes into the English quest page
 *                          (the "Give 2-min feedback" button in quest-en.html)
 *
 * The form is intentionally linear & compact (no hard branching) so it runs
 * robustly and nobody gets stuck.
 */
function buildTimeminatorSurveyEN() {
  var form = FormApp.create('Timeminator – Your Time Check');

  form.setDescription(
    'This survey is anonymous. Details about you and your company are voluntary and only help us ' +
    'put answers in context. You only provide a contact email if you would like a reply. ' +
    'Takes about 3 minutes.'
  );

  // --- Critical settings for anonymous, external participation ---
  form.setCollectEmail(false);          // no email requirement
  form.setRequireLogin(false);          // NO login / not restricted to xixum.ai -> anyone can respond
  form.setLimitOneResponsePerUser(false);
  form.setAllowResponseEdits(false);
  form.setProgressBar(true);
  form.setShowLinkToRespondAgain(false);

  // ============ Section 0 – Where are you right now? ============
  form.addMultipleChoiceItem()
    .setTitle('How would you describe yourself?')
    .setRequired(true)
    .setChoiceValues([
      'I use Timeminator Community (self-hosted, free)',
      'I use Timeminator Pro',
      'I am currently testing / setting it up',
      'I know it but do not use it (yet)',
      'I am here for the first time'
    ]);

  // ============ Section 1 – About you (voluntary) ============
  form.addSectionHeaderItem()
    .setTitle('About you & your company')
    .setHelpText('All voluntary — skip anything you would rather not share.');

  form.addMultipleChoiceItem()
    .setTitle('Your role')
    .showOtherOption(true)
    .setChoiceValues([
      'Freelancer / solo self-employed', 'Agency or studio owner',
      'Consultant', 'Team or project lead', 'Employee on a team',
      'Founder / startup', 'Research & development'
    ]);

  form.addTextItem().setTitle('Industry (e.g. IT, agency, consulting, trades …)');

  form.addMultipleChoiceItem()
    .setTitle('How many people work at your company?')
    .setChoiceValues(['Just me', '2–5', '6–20', '21–50', '51–200', '200+']);

  form.addMultipleChoiceItem()
    .setTitle('How many of them track their time?')
    .setChoiceValues(['Just me', '2–5', '6–20', '20+', 'No one systematically yet']);

  form.addMultipleChoiceItem()
    .setTitle('Legal form')
    .setChoiceValues(['Sole proprietor / freelancer', 'Partnership', 'Private limited (GmbH/UG/Ltd)',
      'Corporation (AG/Inc)', 'Nonprofit / public', 'Prefer not to say']);

  // ============ Section 2 – Your business challenges ============
  form.addSectionHeaderItem()
    .setTitle('Your challenges')
    .setHelpText('The core — this is what we really care about.');

  form.addMultipleChoiceItem()
    .setTitle('How do you track your working time today?')
    .setHelpText('In Timeminator one click starts the clock — instead of reconstructing it from memory in the evening.')
    .setRequired(true)
    .showOtherOption(true)
    .setChoiceValues(['Not at all / from memory', 'Pen & paper', 'Excel / spreadsheet',
      'SaaS tool (Toggl, Clockify, Harvest …)', 'In-house solution', 'Timeminator']);

  var leak = form.addCheckboxItem();
  leak.setTitle('What costs you the most nerves in project and time management?')
    .setHelpText('Multiple choice, please pick at most 3.')
    .setRequired(true)
    .showOtherOption(true)
    .setChoiceValues([
      'I forget to start/stop the timer',
      'At month-end I lose track of where the hours went',
      'Gathering proof for clients/funding',
      'Knowing whether a project is still on budget',
      'Building quotes & invoices from the hours',
      'My data is scattered across other clouds',
      'The team does not play along with tracking'
    ]);
  leak.setValidation(FormApp.createCheckboxValidation().requireSelectAtMost(3).build());

  form.addScaleItem()
    .setTitle('How important is it to you that your time data lives on your own server?')
    .setHelpText('Timeminator is self-hosted — your data stays on your machine, every entry is auditable.')
    .setRequired(true)
    .setBounds(1, 5).setLabels('does not matter', 'essential');

  form.addParagraphTextItem()
    .setTitle('What would give you back the most time day to day?');

  // ============ Section 3 – Features (relevance grid) ============
  form.addSectionHeaderItem().setTitle('The features');

  form.addGridItem()
    .setTitle('How relevant are these features to you?')
    .setRequired(true)
    .setRows([
      'Live timer & dashboard (one click starts the clock)',
      'Time entries & color-coded week calendar',
      'Statistics: hours per client/project, trends, comparison',
      'Proof views (auditable for clients & funding)',
      'Structure: clients → projects → work packages → tasks',
      'Team sentiment (anonymously aggregated)',
      'CSV import with rollback',
      'Self-hosting, roles & permissions'
    ])
    .setColumns(['important', 'nice to have', 'do not need it']);

  form.addParagraphTextItem()
    .setTitle('Which feature do you miss most — or what would you improve first?');

  // ============ Section 4 – Pro block (optional) ============
  form.addSectionHeaderItem()
    .setTitle('Planning & report cockpit (Timeminator Pro)')
    .setHelpText('These features are in Pro. In Community you see the interface but not the data. Everything here is optional.');

  form.addGridItem()
    .setTitle('How interesting would these Pro features be for you? (as a Pro user: how satisfied?)')
    .setRows([
      'Target/budget planning + live report (traffic light, weekly heatmap)',
      'Quotes / cost calculation',
      'Invoices + accounting (lexoffice connector)',
      'Extended role management + audit log',
      'REST API & connectors (Git, calendar)'
    ])
    .setColumns(['would use it', 'interesting', 'do not need it']);

  form.addMultipleChoiceItem()
    .setTitle('Which single feature would most likely move you to Pro?')
    .setChoiceValues([
      'Target/budget planning + live report', 'Quotes / cost calculation',
      'Invoices + accounting', 'Extended role management', 'REST API & connectors',
      'None of them'
    ]);

  form.addMultipleChoiceItem()
    .setTitle('What would be a fair price for Pro?')
    .setChoiceValues(['< €5/month', '€5–10/month', '€10–20/month', '€20–40/month',
      'Prefer a one-time purchase', 'Cannot say']);

  // ============ Section 5 – Wrap-up ============
  form.addSectionHeaderItem().setTitle('To finish');

  form.addScaleItem()
    .setTitle('How likely are you to recommend Timeminator — or to try it?')
    .setBounds(0, 10).setLabels('unlikely', 'very likely');

  form.addParagraphTextItem()
    .setTitle('One sentence: what do you like best — or what is holding you back?');

  form.addTextItem()
    .setTitle('Want a reply or beta access? Then your email (optional)')
    .setHelpText('Only for questions about this survey and an optional beta invite. No marketing.');

  // --- Output links ---
  Logger.log('EDIT:         ' + form.getEditUrl());
  Logger.log('LIVE / SHARE: ' + form.getPublishedUrl());
  return form.getPublishedUrl();
}
