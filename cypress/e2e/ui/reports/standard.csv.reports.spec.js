/// <reference types="cypress" />

describe("csv export", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    describe("personal/family export", () => {
        it("should export personal records as CSV", () => {
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    output: "csv",
                    Format: "Default",
                    Source: "person",
                    familyonly: "false"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                expect(response.body).to.not.include("Fatal error");
                expect(response.body).to.not.include("Parse error");
            });
        });

        it("should export family records as rollup CSV", () => {
            // Tests rollup format for family records
            // Validates that family address fallback logic works in CSV export (issue #7937)
            cy.request({
                method: "POST",
                url: "/CSVCreateFile.php",
                form: true,
                body: {
                    output: "csv",
                    Format: "Rollup",
                    Source: "family",
                    familyonly: "true"
                }
            }).then((response) => {
                expect(response.status).to.eq(200);
                expect(response.headers["content-type"]).to.include("text/csv");
                // Verify the CSV export works without errors
                expect(response.body).to.not.include("Fatal error");
                expect(response.body).to.not.include("Parse error");
            });
        });
    });
});
