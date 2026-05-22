-- Recalculare rating_avg + rating_count la INSERT/UPDATE/DELETE pe reviews
CREATE
    OR REPLACE FUNCTION recalc_camping_rating ()
    RETURNS TRIGGER AS $$

DECLARE target_id INT := COALESCE(NEW.camping_id, OLD.camping_id);
    -- camping_id afectat, prima valoare nenula

BEGIN
    UPDATE campings
    SET rating_avg = (
        SELECT ROUND(AVG(rating)::NUMERIC, 2)
        FROM reviews
        WHERE camping_id = target_id
    )
      ,rating_count = (
        SELECT COUNT(*)
        FROM reviews
        WHERE camping_id = target_id
    )
      ,updated_at = NOW()
    WHERE id = target_id;

    RETURN COALESCE(NEW, OLD);
END;$$

    LANGUAGE PLPGSQL;

CREATE TRIGGER trg_recalc_rating
    AFTER INSERT
        OR

        UPDATE
        OR

        DELETE ON reviews
    FOR EACH ROW

EXECUTE FUNCTION recalc_camping_rating();

-- Auto-update la updated_at pe campings
CREATE
    OR REPLACE FUNCTION set_updated_at ()
    RETURNS TRIGGER AS $$

BEGIN
    NEW.updated_at = NOW();

    RETURN NEW;
END;$$

    LANGUAGE PLPGSQL;

CREATE TRIGGER trg_campings_updated_at BEFORE

    UPDATE ON campings
    FOR EACH ROW

EXECUTE FUNCTION set_updated_at();
