// Afficher le modal automatiquement au chargement de la page
document.addEventListener("DOMContentLoaded", function () {
    const devWarningModal = new bootstrap.Modal(document.getElementById('devWarningModal'));
    devWarningModal.show();
});

function calcul() {
    // helper : parse float with comma support
    const parseNumber = (s) => {
        if (s === undefined || s === null || s === "") return 0;
        return parseFloat(String(s).replace(',', '.')) || 0;
    };

    let totalHSaisie = 0;
    let totalHNorm = 0;
    let totalH25 = 0;
    let totalHRepComp = 0;
    let totalHCompl = 0;

    // Liste des inputs HSaisie (en ordre du DOM)
    const allHSaisieInputs = Array.from(document.getElementsByClassName("HSaisie"));

    // Réinitialisation de tous les champs calculés (ON vide tout)
    const allCalculatedFields = document.querySelectorAll(
        ".HNorm, .HS25, .HS50, .HCompl, .HRepComp, .dimancheHRepComp"
    );
    allCalculatedFields.forEach(field => field.value = "");

    const isSaisonnier = document.getElementById("saisonnierCheckbox")?.checked;

    let lastRowWithHSaisie = null;

    // ---- 1) Parcours des lignes : calcul des HNorm / HS25 / HS50 (progressif, cumulatif) ----
    for (let InputHSaisie of allHSaisieInputs) {
        const raw = InputHSaisie.value;
        const hSaisie = parseNumber(raw);

        // cumul hebdo (on garde le comportement "au fil de la semaine")
        totalHSaisie += hSaisie;

        // dernière ligne non vide
        if (hSaisie > 0) {
            lastRowWithHSaisie = InputHSaisie.closest("tr");
        }

        // alerte si > 60
        if (totalHSaisie > 60) {
            const alertModal = new bootstrap.Modal(document.getElementById('alertModal'));
            alertModal.show();
        }

        // éléments du jour (id attendu : ex "jeudiH", "lundiH", etc.)
        let jour = InputHSaisie.id.split('H')[0];
        const elHNorm = document.getElementById(jour + "HNorm");
        const elHS25 = document.getElementById(jour + "HS25");
        const elHS50 = document.getElementById(jour + "HS50");

        // si éléments manquants, on saute (sécurité)
        if (!elHNorm || !elHS25 || !elHS50) continue;

        // on vide ces champs pour la ligne courante (déjà fait globalement, mais répété pour sûreté)
        elHNorm.value = "";
        elHS25.value = "";
        elHS50.value = "";

        // logique de répartition (conserve ton fonctionnement initial)
        if (hSaisie <= 0) {
            // rien à faire
        } else if (totalHSaisie <= 35) {
            elHNorm.value = hSaisie;
            totalHNorm += hSaisie;
        } else if (totalHSaisie <= 43) {
            if (totalHNorm < 35) {
                const normHours = Math.max(0, 35 - totalHNorm);
                const hs25 = Math.max(0, totalHSaisie - 35); // part dépassant 35 à ce stade
                elHNorm.value = normHours;
                elHS25.value = hs25;
                totalHNorm += normHours;
                totalH25 += hs25;
            } else {
                elHS25.value = hSaisie;
                totalH25 += hSaisie;
            }
        } else {
            // totalHSaisie > 43
            if (totalH25 < 8) {
                const remaining25 = Math.max(0, 8 - totalH25);
                const hs25 = Math.min(remaining25, hSaisie);
                const hs50 = Math.max(0, hSaisie - hs25);
                elHS25.value = hs25;
                elHS50.value = hs50;
                totalH25 += hs25;
            } else {
                elHS50.value = hSaisie;
            }
        }
    }

    // ---- 2) Calculs GLOBAUX après la boucle (on ne touche plus les HCompl dans la boucle) ----

    // HRepComp (repos compensateur global) — inchangé sauf affichage à la fin
    if (!isSaisonnier) {
        if (totalHSaisie >= 49 && totalHSaisie <= 56) {
            totalHRepComp = (totalHSaisie - 48) * 0.25;
        } else if (totalHSaisie >= 57) {
            // au-delà de 56 (on accepte >56 jusqu'à +inf ici, tu peux limiter à 60 si besoin)
            totalHRepComp = (56 - 48) * 0.25;
            totalHRepComp += (totalHSaisie - 56) * 0.5;
        }

        // Ajouter heures du dimanche (si présent)
        const hDimanche = parseNumber(document.querySelector(".dimancheHSaisie")?.value);
        if (hDimanche > 0) totalHRepComp += hDimanche;

        // affichage UNIQUEMENT sur la dernière ligne saisie (premier .HRepComp dans la row)
        if (lastRowWithHSaisie) {
            const targets = lastRowWithHSaisie.querySelectorAll(".HRepComp");
            if (targets.length) targets[0].value = totalHRepComp.toFixed(2);
        }
    }

    // HCompl (HComp) calcul global progressif (dès 49h)
    if (totalHSaisie >= 49) {
        if (totalHSaisie <= 56) {
            totalHCompl = (totalHSaisie - 48) * 0.25;
        } else {
            totalHCompl = (56 - 48) * 0.25;
            totalHCompl += (totalHSaisie - 56) * 0.5;
        }

        // affichage UNIQUEMENT sur la dernière ligne saisie (premier .HCompl dans la row)
        if (lastRowWithHSaisie) {
            const targetsCompl = lastRowWithHSaisie.querySelectorAll(".HCompl");
            if (targetsCompl.length) targetsCompl[0].value = totalHCompl.toFixed(2);
        }
    }

    // Heures dimanche en HS50 (champ séparé)
    const dimancheHS50El = document.getElementById("dimancheHS50");
    const dimancheSaisieEl = document.getElementsByClassName("dimancheHSaisie")[0];
    if (dimancheHS50El) dimancheHS50El.value = dimancheSaisieEl ? dimancheSaisieEl.value : "";

    // Total heures saisies
    const totalEl = document.getElementById("totalHsaisie");
    if (totalEl) totalEl.value = totalHSaisie;
}
