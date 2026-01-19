-- RGW cycle invoices
CREATE TABLE llx_rgw_cycle_facture (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer NOT NULL,
	fk_cycle integer NOT NULL,
	fk_facture integer NOT NULL,
	rg_amount_ttc double(24,8) NOT NULL DEFAULT 0,
	rg_paid_ttc double(24,8) NOT NULL DEFAULT 0,
	multicurrency_rg_amount_ttc double(24,8) NOT NULL DEFAULT 0,
	multicurrency_rg_paid_ttc double(24,8) NOT NULL DEFAULT 0,
	datec datetime NULL,
	tms timestamp
) ENGINE=innodb;

CREATE UNIQUE INDEX uk_rgw_cycle_facture ON llx_rgw_cycle_facture (entity, fk_cycle, fk_facture);
CREATE INDEX idx_rgw_cycle_facture_cycle ON llx_rgw_cycle_facture (fk_cycle);
