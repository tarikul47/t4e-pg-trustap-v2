Hi Tarikul,

Thank you for your response. I understand your concerns and would like to explain in more detail how this solution works in practice to address your requirements.

On your database, you would maintain both a Guest User ID and a Full User ID. Every transaction begins by using the Guest User ID.

In Trustap, we do not upgrade or convert a Guest User ID into a Full User ID; instead, a new Full User account is created. This means a user on your platform can maintain a valid Guest User ID and a Full User account simultaneously.

This structure provides the flexibility you need: you can have a transaction claimed to a Full User ID once the buyer has confirmed they are satisfied, while simultaneously using a Guest User ID for other transactions until you are ready to claim them.

A user on your platform only ever needs to have once Trustap account in this scenario and you can claim a transaction on their behalf using the endpoint. You could trigger this claim once the buyer notifies you they are happy, so it can all be automated.

I hope this clears it up for you. If not let me know and we can have a brief call to answer any questions you might have.

Best regards,
Eamonn Mooney