CREATE TABLE IF NOT EXISTS benefit_budgets (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 program VARCHAR(30) NOT NULL,
 fiscal_year SMALLINT NOT NULL,
 title VARCHAR(120) NOT NULL,
 allocation_cents BIGINT NOT NULL,
 created_by INT NOT NULL,
 created_by_name VARCHAR(100) NOT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_benefit_budget_year (fiscal_year,program),
 UNIQUE KEY uq_budget_program_year_title (program,fiscal_year,title)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS benefit_budget_adjustments (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 budget_id BIGINT UNSIGNED NOT NULL,
 amount_cents BIGINT NOT NULL,
 reason VARCHAR(1000) NOT NULL,
 recorded_by INT NOT NULL,
 recorded_by_name VARCHAR(100) NOT NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 INDEX idx_budget_adjustment (budget_id,id),
 CONSTRAINT fk_benefit_adjustment_budget FOREIGN KEY (budget_id) REFERENCES benefit_budgets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS benefit_distributions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 budget_id BIGINT UNSIGNED NOT NULL,
 profile_id VARCHAR(255) NOT NULL,
 application_id VARCHAR(255) NOT NULL,
 senior_id_no VARCHAR(255) NOT NULL,
 senior_name VARCHAR(255) NOT NULL,
 barangay VARCHAR(100) NOT NULL,
 program VARCHAR(30) NOT NULL,
 benefit_period CHAR(7) NOT NULL,
 distribution_date DATE NOT NULL,
 item_description VARCHAR(255) NOT NULL,
 quantity INT NOT NULL,
 unit_cost_cents BIGINT NOT NULL,
 total_cost_cents BIGINT NOT NULL,
 reference_no VARCHAR(100) NOT NULL,
 remarks VARCHAR(1000) NOT NULL,
 active_key CHAR(64) NULL,
 active_request_key CHAR(64) NULL,
 recorded_by INT NOT NULL,
 recorded_by_name VARCHAR(100) NOT NULL,
 recorded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 voided_at DATETIME NULL,
 voided_by INT NULL,
 voided_by_name VARCHAR(100) NULL,
 void_reason VARCHAR(1000) NULL,
 UNIQUE KEY uq_distribution_person_period (active_key),
 UNIQUE KEY uq_distribution_once_per_request (active_request_key),
 INDEX idx_distribution_budget (budget_id,voided_at),
 INDEX idx_distribution_scope (barangay,distribution_date),
 CONSTRAINT fk_benefit_distribution_budget FOREIGN KEY (budget_id) REFERENCES benefit_budgets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
