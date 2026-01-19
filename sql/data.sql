-- Email templates for RG Warranty
INSERT INTO llx_c_email_templates (entity, module, type_template, label, lang, subject, content, active)
VALUES
(1, 'rgwarranty', 'rgw_cycle', 'rgwarranty_request', 'fr_FR', 'Demande de restitution de la retenue de garantie', 'Bonjour,\n\nNous vous informons que la réception des travaux est intervenue le __RECEPTION_DATE__.\nVeuillez procéder à la restitution de la retenue de garantie pour le chantier __PROJECT_REF__.\nMontant RG à restituer : __RG_TOTAL__.\n\nCordialement,', 1),
(1, 'rgwarranty', 'rgw_cycle', 'rgwarranty_request', 'en_US', 'Retention release request', 'Hello,\n\nReception occurred on __RECEPTION_DATE__.\nPlease release the retention for project __PROJECT_REF__.\nRetention amount: __RG_TOTAL__.\n\nRegards,', 1),
(1, 'rgwarranty', 'rgw_cycle', 'rgwarranty_reminder', 'fr_FR', 'Relance restitution de la retenue de garantie', 'Bonjour,\n\nNous revenons vers vous concernant la restitution de la retenue de garantie du chantier __PROJECT_REF__.\nMontant restant : __RG_TOTAL__.\n\nCordialement,', 1),
(1, 'rgwarranty', 'rgw_cycle', 'rgwarranty_reminder', 'en_US', 'Retention release reminder', 'Hello,\n\nReminder about the retention release for project __PROJECT_REF__.\nRemaining amount: __RG_TOTAL__.\n\nRegards,', 1);
