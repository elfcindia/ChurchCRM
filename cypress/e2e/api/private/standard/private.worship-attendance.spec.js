/// <reference types="cypress" />

/**
 * Worship greeter attendance API (Add Records permission).
 */
describe("API Worship attendance", () => {
    it("GET /api/worship/attendance/summary returns counts", () => {
        cy.makePrivateAdminAPICall("GET", "/api/worship/attendance/summary", null, 200).then((response) => {
            expect(response.body).to.have.property("date");
            expect(response.body).to.have.property("expectedTotal");
            expect(response.body).to.have.property("attendedExpected");
            expect(response.body).to.have.property("absentTotal");
            expect(response.body).to.have.property("guestAttended");
            expect(response.body).to.have.property("attendedAll");
        });
    });

    it("Returns 401 when not authenticated", () => {
        cy.apiRequest({
            method: "GET",
            url: "/api/worship/attendance/summary",
            failOnStatusCode: false,
        }).then((response) => {
            expect(response.status).to.eq(401);
        });
    });

    it("POST /api/worship/attendance/checkin rejects missing personId", () => {
        cy.makePrivateAdminAPICall("POST", "/api/worship/attendance/checkin", {}, 400).then((response) => {
            expect(response.body).to.have.property("message");
        });
    });

    it("POST /api/worship/attendance/checkin returns 404 for unknown person", () => {
        cy.makePrivateAdminAPICall(
            "POST",
            "/api/worship/attendance/checkin",
            { personId: 999999999 },
            404,
        ).then((response) => {
            expect(response.body).to.have.property("message");
        });
    });
});
