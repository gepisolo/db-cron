CREATE TABLE cronjobs (
    id SERIAL PRIMARY KEY,
    min TEXT,
    hour TEXT,
    dom TEXT,
    mon TEXT,
    dow TEXT,
    func_name TEXT,
    func_args TEXT,
    system_script TEXT,
    active BOOLEAN
);
